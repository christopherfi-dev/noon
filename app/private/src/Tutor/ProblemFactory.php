<?php
declare(strict_types=1);

namespace AlgebraEngine\Tutor;

use AlgebraEngine\Items\ItemFactory;
use AlgebraEngine\Items\Template;
use AlgebraEngine\Math\ScalarParser;
use AlgebraEngine\Support\Ids;

/**
 * Builds the private problem record every typed-answer question needs before
 * it reaches the learner: the wording, the response type, and the answer code
 * computed (round 6 §2.1). A record is never built from an answer the AI
 * stated; the AI's answer is only compared with it.
 *
 * Record fields: problem_id, version, purpose (PRACTICE | CHECK | RETRY),
 * skill_id, source (RECIPE | SPEC | RETRY), kind, spec, template_id, params,
 * variant_id, prompt, response_type, variables, answer, options, numbers,
 * fingerprint, retry_of.
 */
final class ProblemFactory
{
    public const PURPOSES = ['PRACTICE', 'CHECK', 'RETRY'];
    private const KIND_OF_TEMPLATE = [
        'ONE_STEP_ADD' => 'linear_equation', 'ONE_STEP_MULTIPLY' => 'linear_equation', 'TWO_STEP' => 'linear_equation',
        'GROUPED_LINEAR' => 'linear_equation', 'GROUPED_LINEAR_TRANSFER' => 'linear_equation',
        'POSITIVE_DISTRIBUTION' => 'expand', 'DISTRIBUTION_TRANSFER' => 'expand',
        'SIGNED_ADD' => 'signed_arithmetic', 'SIGNED_SUBTRACT' => 'signed_arithmetic', 'SIGNED_MULTIPLY' => 'signed_arithmetic',
        'SIGNED_DIVIDE' => 'signed_arithmetic', 'WHOLE_MULTIPLY_DIVIDE' => 'signed_arithmetic',
        'DISTRIBUTION_CONTRAST' => 'classification', 'GROUPED_FIRST_STEP_CONTRAST' => 'classification',
    ];

    public function __construct(private ItemFactory $factory)
    {
    }

    public function items(): ItemFactory
    {
        return $this->factory;
    }

    /** A problem from a recipe: wording and answer both from the recipe. */
    public function fromRecipe(string $templateId, array $params, string $purpose): array
    {
        $t = $this->factory->get($templateId);
        $objects = $t->mathematicalObjects($params);
        $prompt = $t->render($params);
        return $this->record([
            'purpose' => $purpose,
            'skill_id' => $t->skillId(),
            'source' => 'RECIPE',
            'kind' => self::KIND_OF_TEMPLATE[$templateId],
            'spec' => $objects['equation'] ?? $objects['expression'] ?? null,
            'template_id' => $templateId,
            'params' => $params,
            'variant_id' => $t->variantId(),
            'prompt' => $prompt,
            'response_type' => $t->responseType(),
            'variables' => $objects['variables'] ?? (isset($objects['variable']) ? [$objects['variable']] : []),
            'answer' => self::answer($t, $params),
            'options' => $objects['options'] ?? null,
            'fingerprint' => ItemFactory::fingerprint($templateId, $params),
        ]);
    }

    /**
     * A problem from a computed spec. A check problem's wording is always the
     * matched recipe's (code writes it); a practice problem may carry the AI's
     * own wording, checked separately by the verifier.
     *
     * @param array $computed SpecMath::compute() output
     * @param array|null $match PatternMatcher::match() output
     */
    public function fromSpec(array $computed, ?array $match, string $purpose, string $skillId, ?string $prompt): array
    {
        $template = $match !== null ? $this->factory->get($match['template_id']) : null;
        if ($purpose === 'CHECK') {
            if ($template === null) {
                throw new \LogicException('A check problem needs a matched recipe');
            }
            $prompt = $template->render($match['params']);
        }
        if ($prompt === null || trim($prompt) === '') {
            $prompt = $template !== null ? $template->render($match['params']) : self::defaultWording($computed);
        }
        return $this->record([
            'purpose' => $purpose,
            'skill_id' => $match['skill_id'] ?? $skillId,
            'source' => 'SPEC',
            'kind' => $computed['kind'],
            'spec' => $computed['canonical'],
            'template_id' => $match['template_id'] ?? null,
            'params' => $match['params'] ?? null,
            'variant_id' => $match['variant_id'] ?? null,
            'prompt' => trim($prompt),
            'response_type' => $computed['response_type'],
            'variables' => $computed['variables'],
            'answer' => $template !== null ? self::answer($template, $match['params']) : $computed['answer'],
            'options' => null,
            'fingerprint' => $match !== null ? ItemFactory::fingerprint($match['template_id'], $match['params']) : hash('sha256', 'spec|' . $computed['kind'] . '|' . $computed['canonical']),
        ]);
    }

    /** §5.5: the slipped check problem shown again for a retry. It never counts. */
    public function retryOf(array $problem): array
    {
        $copy = $problem;
        unset($copy['problem_id'], $copy['version']);
        return $this->record(['purpose' => 'RETRY', 'source' => 'RETRY', 'retry_of' => $problem['problem_id']] + $copy);
    }

    /** Code-written wording for a spec with no recipe and no usable AI wording. */
    public static function defaultWording(array $computed): string
    {
        return match ($computed['kind']) {
            'linear_equation' => 'Solve ' . $computed['display'] . ' for x.',
            'expand' => 'Write ' . $computed['display'] . ' without parentheses, with like terms combined.',
            default => 'Calculate ' . $computed['display'] . '.',
        };
    }

    private static function answer(Template $t, array $params): array
    {
        $spec = $t->answerSpec($params);
        return ['kind' => $spec['kind'], 'expected' => $spec['expected'], 'requested_form' => $spec['requested_form']];
    }

    private function record(array $p): array
    {
        $p += ['retry_of' => null, 'options' => null, 'template_id' => null, 'params' => null, 'variant_id' => null];
        $optionText = $p['options'] === null ? '' : ' ' . implode(' ', array_column($p['options'], 'text'));
        return [
            'problem_id' => Ids::make('prb'),
            'version' => 1,
            'purpose' => $p['purpose'],
            'skill_id' => $p['skill_id'],
            'source' => $p['source'],
            'kind' => $p['kind'],
            'spec' => $p['spec'],
            'template_id' => $p['template_id'],
            'params' => $p['params'],
            'variant_id' => $p['variant_id'],
            'prompt' => $p['prompt'],
            'response_type' => $p['response_type'],
            'variables' => $p['variables'],
            'answer' => $p['answer'],
            'options' => $p['options'],
            'numbers' => Numbers::digits($p['prompt'] . $optionText),
            'fingerprint' => $p['fingerprint'],
            'retry_of' => $p['retry_of'],
        ];
    }

    /**
     * The numbers of the problem's main equation or expression, sorted ("7,11"
     * for 7(x + 11)). Two problems with the same set show the same mathematics
     * (one may be the other's answer), so a check problem must not repeat one.
     */
    public static function numberSet(array $problem): string
    {
        $stem = preg_split('/\n(?=[A-D]\) )/u', $problem['prompt'])[0];
        $main = \AlgebraEngine\Support\ProblemText::main($stem);
        $n = Numbers::digits($main === '' ? $stem : $main);
        sort($n);
        return implode(',', $n);
    }

    /**
     * True when the answer's value is also a number written in the problem
     * (x = 3 in 3(x + 4) = 21). Round 5.1 post-beta item: avoided at the source.
     */
    public static function answerShownInProblem(array $problem): bool
    {
        if ($problem['answer']['kind'] !== 'exact_rational') {
            return false;
        }
        $v = ScalarParser::parse((string) $problem['answer']['expected']);
        return $v->isInteger() && in_array((string) abs($v->num()), $problem['numbers'], true);
    }
}
