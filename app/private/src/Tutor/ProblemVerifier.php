<?php
declare(strict_types=1);

namespace AlgebraEngine\Tutor;

use AlgebraEngine\Curriculum\BetaScope;
use AlgebraEngine\Math\ExpressionParser;
use AlgebraEngine\Math\MathInputError;
use AlgebraEngine\Math\Polynomial;
use AlgebraEngine\Math\ScalarParser;

/**
 * Problem verification (round 6 §4.2; architecture v1.6 §8). The AI proposes
 * at most one next problem per turn, as its own spec, a pre-generated recipe
 * problem, or the retry of a slipped check problem. Code computes the answer
 * and rejects the problem when the AI's stated answer differs, there is no
 * unique solution, or numbers are out of range. Check problems must also
 * match their skill's pattern (a recipe used in reverse), be fresh, and not
 * contain their own single-number answer or its opposite (round 8 §3.6:
 * practice problems may use any numbers). Choice check problems come only from the contrast
 * recipes. The displayed wording of every problem, practice or check, is
 * rendered by code from its verified spec (round 7 §8.1), so wording and spec
 * correspond by construction; the AI's prompt is recorded, never displayed.
 *
 * Proposal (the envelope's item): purpose practice|check|retry, skill_id,
 * source recipe|spec|retry, recipe_ref, spec {kind, expression}, prompt,
 * stated_answer.
 */
final class ProblemVerifier
{
    /** Round 8 §3.6: the failure a prepared check problem may replace after one repair. */
    public const ANSWER_IN_QUESTION = 'ANSWER_IN_QUESTION';

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
        if ($purpose === 'CHECK' && ProblemFactory::answerShownInProblem($problem)) {
            // Round 8 §3.6: check problems only; practice problems are accepted whatever their numbers.
            return self::fail(['the question shows its own answer: the answer ' . $problem['answer']['expected'] . ' is also a number in the question; choose numbers so they differ'])
                + ['code' => self::ANSWER_IN_QUESTION, 'rejected' => $problem];
        }
        return ['ok' => true, 'errors' => [], 'problem' => $problem, 'dropped_prompt_chars' => $dropped];
    }

    /**
     * Round 8 §3.6: a prepared check problem for the same skill, to stand in
     * for a check problem that still shows its own answer after one repair.
     * It must be fresh, satisfy the rule, and for Q07 and E06 be of the same
     * kind of question when one is prepared. Null when none is available.
     *
     * @param array<string,array> $recipes prepared problems keyed by reference
     * @return array{ref:string,problem:array}|null
     */
    public function preparedCheck(array $rejected, array $recipes, array $ctx): ?array
    {
        $candidates = [];
        foreach ($recipes as $ref => $p) {
            if ($p['skill_id'] !== $rejected['skill_id'] || $p['response_type'] === 'classification') {
                continue;
            }
            $p['purpose'] = 'CHECK';
            if (ProblemFactory::answerShownInProblem($p) || in_array($p['fingerprint'], $ctx['used_fingerprints'] ?? [], true)
                || in_array(ProblemFactory::numberSet($p), $ctx['used_number_sets'][$p['skill_id']] ?? [], true)) {
                continue;
            }
            $candidates[] = ['ref' => (string) $ref, 'problem' => $p, 'same_kind' => $p['variant_id'] === $rejected['variant_id']];
        }
        usort($candidates, fn ($a, $b) => (int) $b['same_kind'] <=> (int) $a['same_kind']);
        return $candidates === [] ? null : ['ref' => $candidates[0]['ref'], 'problem' => $candidates[0]['problem']];
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
        return [$problem, mb_strlen(trim((string) ($proposal['prompt'] ?? '')))];
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
        $problem = $this->problems->fromSpec($computed, $match, $purpose, $skill, null);
        return [$problem, mb_strlen(trim((string) ($proposal['prompt'] ?? '')))];
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
