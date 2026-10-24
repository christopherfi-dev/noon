<?php
declare(strict_types=1);

namespace AlgebraEngine\Tutor;

use AlgebraEngine\Checking\CheckOutcome;
use AlgebraEngine\Checking\ClassificationChecker;
use AlgebraEngine\Checking\EquationStepCheck;
use AlgebraEngine\Checking\ExactRationalChecker;
use AlgebraEngine\Checking\PolynomialChecker;
use AlgebraEngine\Items\Fmt;
use AlgebraEngine\Items\ItemFactory;
use AlgebraEngine\Math\ScalarParser;

/**
 * The calculator and answer checker: the existing exact checkers against the
 * answer code computed, the recipe's error signatures when the problem has a
 * recipe, and slip confirmation (§4.4). The verdict here is the only verdict;
 * nothing the AI writes can change it.
 */
final class AnswerCheck
{
    public const EQUIVALENT_NOT_STEP_LABEL = 'has the same solution as the starting equation, but its sides are not the equation after this step';

    /** Round 9 §4.4: the observation sent for an answer one away from the solution of an equation; it states what the answer did. */
    public const DIFFERENCE_LABEL = 'The answer {got} differs from the solution by {difference}.';
    public const DIFFERENCE_CODES = ['ARITH_FINAL_PLUS', 'ARITH_FINAL_MINUS', 'ARITH_OFF_BY_ONE'];

    public function __construct(private ItemFactory $factory)
    {
    }

    /** Verdict only. */
    public static function outcome(array $problem, string $text): CheckOutcome
    {
        $a = $problem['answer'];
        return match ($a['kind']) {
            'exact_rational' => ExactRationalChecker::check(ScalarParser::parse((string) $a['expected']), $text, in_array('x', $problem['variables'] ?? [], true) ? 'x' : null),
            'polynomial' => PolynomialChecker::check((string) $a['expected'], $text, $problem['variables'] ?: ['x'], $a['requested_form']),
            'classification' => ClassificationChecker::check((array) $a['expected'], array_column($problem['options'] ?? [], 'letter') ?: ['A', 'B', 'C', 'D'], $text),
            'equation_step' => EquationStepCheck::check($a, $text),
            default => throw new \LogicException('Unknown answer kind ' . $a['kind']),
        };
    }

    /**
     * @return array{verdict:string,normalized:?string,observation:string,scope:string,clarification:?string,form_issue:?string,
     *   signatures:list<array{code:string,label:string,branch:?string}>,slip:?array}
     */
    public function check(array $problem, string $text): array
    {
        $o = self::outcome($problem, $text);
        $signatures = [];
        if ($problem['template_id'] !== null && $problem['params'] !== null) {
            $signatures = $this->factory->get($problem['template_id'])->matchSignatures($problem['params'], $text, $o);
        }
        foreach ($signatures as $i => $s) {
            if (in_array($s['code'], self::DIFFERENCE_CODES, true) && $o->normalizedResponse !== null) {
                $got = ScalarParser::parse($o->normalizedResponse);
                $difference = $got->sub(ScalarParser::parse((string) $problem['answer']['expected']))->abs();
                $signatures[$i]['label'] = strtr(self::DIFFERENCE_LABEL, ['{got}' => Fmt::n($got), '{difference}' => Fmt::n($difference)]);
            }
        }
        if ($problem['answer']['kind'] === 'equation_step' && $o->observation === EquationStepCheck::EQUIVALENT_NOT_STEP) {
            // Round 9 §4.5: an observation for the tutor; it states what the entry did.
            $signatures[] = ['code' => EquationStepCheck::EQUIVALENT_NOT_STEP, 'label' => self::EQUIVALENT_NOT_STEP_LABEL, 'branch' => null];
        }
        return [
            'verdict' => $o->verdict,
            'normalized' => $o->normalizedResponse,
            'observation' => $o->observation,
            'scope' => $o->scope,
            'clarification' => $o->clarification,
            'form_issue' => $o->formIssue,
            'signatures' => $signatures,
            'slip' => SlipCheck::eligible($problem, $o, $signatures),
        ];
    }
}
