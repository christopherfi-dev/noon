<?php
declare(strict_types=1);

namespace AlgebraEngine\Tutor;

use AlgebraEngine\Curriculum\BetaScope;
use AlgebraEngine\Math\ExpressionParser;
use AlgebraEngine\Math\MathInputError;
use AlgebraEngine\Math\Polynomial;
use AlgebraEngine\Math\ScalarParser;

/**
 * Round 6 §4.2: problem verification. The AI proposes at most one next
 * problem per turn, as its own spec, a pre-generated recipe problem, or the
 * retry of a slipped check problem. Code computes the answer and rejects the
 * problem when the AI's stated answer differs, there is no unique solution,
 * numbers are out of range, or the wording contains its own answer. Check
 * problems must also match their skill's pattern (a recipe used in reverse)
 * and be fresh; their wording is always code's. Choice check problems come
 * only from the contrast recipes.
 *
 * Proposal (the envelope's item): purpose practice|check|retry, skill_id,
 * source recipe|spec|retry, recipe_ref, spec {kind, expression}, prompt,
 * stated_answer.
 */
final class ProblemVerifier
{
    public const MAX_PROMPT = 400;

    public function __construct(private ProblemFactory $problems, private PatternMatcher $patterns)
    {
    }

    /**
     * @param array{recipes:array<string,array>,used_fingerprints:list<string>,retry_target:?array,limits?:array,used_number_sets?:array<string,list<string>>} $ctx
     * @return array{ok:bool,errors:list<string>,problem:?array,dropped_prompt_chars:int}
     */
    public function verify(array $proposal, array $ctx): array
    {
        $purpose = strtoupper((string) ($proposal['purpose'] ?? ''));
        $skill = (string) ($proposal['skill_id'] ?? '');
        $source = (string) ($proposal['source'] ?? '');
        if (!in_array($purpose, ProblemFactory::PURPOSES, true)) {
            return self::fail(['item.purpose must be practice, check or retry']);
        }
        if (!BetaScope::contains($skill)) {
            return self::fail(['item.skill_id must be one of the ten skills in this tutor: ' . implode(', ', BetaScope::SKILLS)]);
        }
        try {
            [$problem, $dropped] = match ($source) {
                'retry' => $this->retry($purpose, $skill, $ctx),
                'recipe' => $this->recipe($proposal, $purpose, $skill, $ctx),
                'spec' => $this->spec($proposal, $purpose, $skill, $ctx),
                default => throw new SpecError('SOURCE', 'item.source must be recipe, spec or retry'),
            };
        } catch (SpecError $e) {
            return self::fail([$e->getMessage()]);
        }
        if ($purpose === 'CHECK' && in_array($problem['fingerprint'], $ctx['used_fingerprints'], true)) {
            return self::fail(['a check question must be fresh: this one was already shown in this session']);
        }
        if ($purpose === 'CHECK' && in_array(ProblemFactory::numberSet($problem), $ctx['used_number_sets'][$skill] ?? [], true)) {
            return self::fail(['a check question must stand alone: it uses the same numbers (' . ProblemFactory::numberSet($problem) . ') as a question already shown for ' . $skill . '; choose different numbers']);
        }
        if ($purpose !== 'RETRY' && ProblemFactory::answerShownInProblem($problem)) {
            return self::fail(['the question shows its own answer: the answer ' . $problem['answer']['expected'] . ' is also a number in the question; choose numbers so they differ']);
        }
        return ['ok' => true, 'errors' => [], 'problem' => $problem, 'dropped_prompt_chars' => $dropped];
    }

    /** @return array{0:array,1:int} */
    private function retry(string $purpose, string $skill, array $ctx): array
    {
        $target = $ctx['retry_target'] ?? null;
        if ($purpose !== 'RETRY' || $target === null) {
            throw new SpecError('RETRY', 'a retry is only possible right after a slip on a check question, as purpose retry with source retry');
        }
        if ($target['skill_id'] !== $skill) {
            throw new SpecError('RETRY', 'a retry repeats the slipped check question, whose skill is ' . $target['skill_id']);
        }
        return [$this->problems->retryOf($target), 0];
    }

    /** @return array{0:array,1:int} */
    private function recipe(array $proposal, string $purpose, string $skill, array $ctx): array
    {
        if ($purpose === 'RETRY') {
            throw new SpecError('RETRY', 'a retry uses source retry');
        }
        $ref = (string) ($proposal['recipe_ref'] ?? '');
        $recipe = $ctx['recipes'][$ref] ?? null;
        if ($recipe === null) {
            throw new SpecError('RECIPE', 'item.recipe_ref must be one of the prepared problems: ' . implode(', ', array_keys($ctx['recipes'])));
        }
        if ($recipe['skill_id'] !== $skill) {
            throw new SpecError('RECIPE', 'recipe ' . $ref . ' is a ' . $recipe['skill_id'] . ' problem, not ' . $skill);
        }
        $stated = $proposal['stated_answer'] ?? null;
        if ($stated !== null && $stated !== '' && !self::sameAnswer($recipe, $stated)) {
            throw new SpecError('STATED_ANSWER', 'the stated answer differs from the answer code computed for ' . $ref);
        }
        $problem = $recipe;
        $problem['purpose'] = $purpose;
        $wording = trim((string) ($proposal['prompt'] ?? ''));
        $dropped = 0;
        if ($purpose === 'CHECK' || $recipe['response_type'] === 'classification') {
            $dropped = mb_strlen($wording); // code writes check and choice wording
        } elseif ($wording !== '') {
            $this->checkWording($wording, $recipe, $recipe['numbers']);
            $problem['prompt'] = $wording;
            $problem['numbers'] = Numbers::digits($wording);
        }
        return [$problem, $dropped];
    }

    /** @return array{0:array,1:int} */
    private function spec(array $proposal, string $purpose, string $skill, array $ctx): array
    {
        if ($purpose === 'RETRY') {
            throw new SpecError('RETRY', 'a retry uses source retry');
        }
        $spec = $proposal['spec'] ?? null;
        if (!is_array($spec) || !is_string($spec['kind'] ?? null) || !is_string($spec['expression'] ?? null)) {
            throw new SpecError('SPEC', 'item.spec must have kind and expression');
        }
        $computed = SpecMath::compute($spec['kind'], $spec['expression'], $ctx['limits'] ?? []);
        $stated = $proposal['stated_answer'] ?? null;
        if (!is_string($stated) || trim($stated) === '') {
            throw new SpecError('STATED_ANSWER', 'item.stated_answer is required for a spec');
        }
        $draft = ['answer' => $computed['answer'], 'variables' => $computed['variables'] ?: ['x'], 'response_type' => $computed['response_type']];
        if (!self::sameAnswer($draft, $stated)) {
            throw new SpecError('STATED_ANSWER', 'the stated answer ' . $stated . ' differs from the answer code computed from the spec');
        }
        $match = $this->patterns->match($computed['kind'], $computed['nodes'], $skill);
        if ($purpose === 'CHECK') {
            if ($match === null) {
                $names = array_values(array_filter(PatternMatcher::DESCRIPTIONS, fn ($d) => str_starts_with($d, $skill . ' ') || str_starts_with($d, $skill . ':')));
                throw new SpecError('OFF_PATTERN', 'a check question for ' . $skill . ' must match its pattern' . ($names === [] ? ' (this skill has no typed pattern; use a prepared problem)' : ': ' . implode('; ', $names)));
            }
            $problem = $this->problems->fromSpec($computed, $match, 'CHECK', $skill, null);
            return [$problem, mb_strlen(trim((string) ($proposal['prompt'] ?? '')))];
        }
        $match ??= $this->patterns->match($computed['kind'], $computed['nodes']);
        $wording = trim((string) ($proposal['prompt'] ?? ''));
        $numbers = [];
        foreach ($computed['nodes'] as $node) {
            foreach (SpecMath::literals($node) as $v) {
                $numbers[] = (string) abs($v->num());
            }
        }
        $numbers = array_values(array_unique($numbers));
        $problem = $this->problems->fromSpec($computed, $match, $purpose, $skill, $wording === '' ? null : $wording);
        if ($wording !== '') {
            $this->checkWording($wording, $problem, $numbers);
        }
        return [$problem, 0];
    }

    /**
     * Practice wording is the AI's own; the math must still be the spec's.
     *
     * @param list<string> $numbers numbers the spec contains
     */
    private function checkWording(string $wording, array $problem, array $numbers): void
    {
        if (mb_strlen($wording) > self::MAX_PROMPT) {
            throw new SpecError('WORDING', 'item.prompt is too long (limit ' . self::MAX_PROMPT . ' characters)');
        }
        $written = Numbers::all($wording);
        $missing = array_values(array_diff($numbers, $written));
        if ($missing !== []) {
            throw new SpecError('WORDING', 'item.prompt must show the problem the spec describes; these numbers from the spec are missing: ' . implode(', ', $missing));
        }
        $reasons = TextGuard::violations($wording, [$problem]);
        if ($reasons !== []) {
            throw new SpecError('WORDING', 'item.prompt: ' . implode('; ', $reasons));
        }
    }

    /** The AI's stated answer equals the answer code computed. */
    public static function sameAnswer(array $problem, mixed $stated): bool
    {
        $a = $problem['answer'];
        try {
            return match ($a['kind']) {
                'exact_rational' => (is_string($stated) || is_int($stated)) && ScalarParser::parse((string) $stated, 'x')->equals(ScalarParser::parse((string) $a['expected'])),
                'polynomial' => is_string($stated) && Polynomial::fromNode(ExpressionParser::parse($stated, $problem['variables'] ?: ['x']))
                    ->equals(Polynomial::fromNode(ExpressionParser::parse((string) $a['expected'], $problem['variables'] ?: ['x']))),
                'classification' => self::letters($stated) === self::letters($a['expected']),
                default => false,
            };
        } catch (MathInputError) {
            return false;
        }
    }

    /** @return list<string> */
    private static function letters(mixed $v): array
    {
        $list = is_array($v) ? $v : preg_split('/[\s,;]+/', strtoupper((string) $v), -1, PREG_SPLIT_NO_EMPTY);
        $list = array_map(fn ($x) => strtoupper(trim((string) $x)), $list ?: []);
        sort($list);
        return array_values(array_unique($list));
    }

    private static function fail(array $errors): array
    {
        return ['ok' => false, 'errors' => $errors, 'problem' => null, 'dropped_prompt_chars' => 0];
    }
}
