<?php
declare(strict_types=1);

namespace AlgebraEngine\Items;

use AlgebraEngine\Math\ExpressionParser;
use AlgebraEngine\Math\MathInputError;
use AlgebraEngine\Math\Polynomial;
use AlgebraEngine\Provider\DisclosureGuard;
use AlgebraEngine\Workflow\ReadingGuard;

/**
 * Round 6 (architecture 1.3, §6.1): the host's check on a smaller guided
 * question the model proposes for this learner's error. The host evaluates
 * step_expression and uses the step only when:
 * - the expression parses and is a number or a linear expression in the step's variables;
 * - every number in it comes from the problem or earlier steps;
 * - its value differs from the answer of the step it breaks down;
 * - the question asks something and does not state its own answer, or the
 *   answer of any remaining step.
 * Otherwise the planner's own smaller step is used.
 */
final class StepProposalCheck
{
    /**
     * @param array{question:string,step_expression:string} $proposal
     * @param array<string,mixed> $replaced the planner's smaller step this proposal would replace
     * @param array<string,mixed> $parent the step being broken down
     * @param array<string,true> $allowedNumbers numbers in the problem and earlier steps
     * @param list<array> $protected remaining steps' answers (DisclosureGuard form)
     * @param callable(string):list<string> $textErrors the learner-text guards (internal terms, support claims)
     * @return array{ok:bool,reasons:list<string>,step:?array}
     */
    public static function check(array $proposal, array $replaced, array $parent, array $allowedNumbers, array $protected, callable $textErrors): array
    {
        $vars = $replaced['variables'] ?: ['x'];
        $reasons = [];
        $expr = trim($proposal['step_expression']);
        $question = trim($proposal['question']);
        $value = null;
        try {
            $value = Polynomial::fromNode(ExpressionParser::parse($expr, $vars));
        } catch (MathInputError $e) {
            $reasons[] = 'step_expression could not be evaluated (' . $e->getMessage() . ')';
        }
        if ($value !== null && $value->degree() > 1) {
            $reasons[] = 'step_expression must be a number or a linear expression';
        }
        foreach (ReadingGuard::numbers($expr) as $n) {
            if (!isset($allowedNumbers[$n])) {
                $reasons[] = 'step_expression uses the number ' . $n . ', which is not in the problem or an earlier step';
            }
        }
        if (!str_contains($question, '?')) {
            $reasons[] = 'the question must ask the learner something';
        }
        foreach ($textErrors($question) as $e) {
            $reasons[] = 'question: ' . $e;
        }
        if ($reasons !== [] || $value === null) {
            return ['ok' => false, 'reasons' => array_values(array_unique($reasons)), 'step' => null];
        }
        $constant = $value->constantValue();
        $answer = $constant !== null
            ? ['kind' => 'exact_rational', 'expected' => $constant->toString(), 'requested_form' => 'single_number', 'units' => null, 'tolerance' => null, 'rubric' => []]
            : ['kind' => 'polynomial', 'expected' => $value->toString(), 'requested_form' => 'expanded', 'units' => null, 'tolerance' => null, 'rubric' => []];
        if (self::sameAnswer($answer, $parent['answer'], $vars)) {
            $reasons[] = 'the smaller question has the same answer as the step it breaks down';
        }
        if (DisclosureGuard::violations($question, [['answer' => $answer, 'variables' => $vars, 'options' => null, 'active' => true]]) !== []) {
            $reasons[] = 'the question states its own answer';
        }
        if (DisclosureGuard::violations($question, $protected) !== []) {
            $reasons[] = 'the question states the answer of a remaining step';
        }
        if ($reasons !== []) {
            return ['ok' => false, 'reasons' => $reasons, 'step' => null];
        }
        return ['ok' => true, 'reasons' => [], 'step' => [
            'id' => $replaced['id'], 'prompt' => $question, 'alt_prompt' => null, 'response_type' => $constant !== null ? 'scalar' : 'expression',
            'answer' => $answer, 'variables' => $vars, 'options' => null, 'exercises' => $replaced['exercises'], 'smaller' => [], 'final' => false,
            'wrong_choice_fact' => null, 'source' => 'MODEL', 'step_expression' => $expr,
        ]];
    }

    private static function sameAnswer(array $a, array $b, array $vars): bool
    {
        if ($a['kind'] === 'classification' || $b['kind'] === 'classification') {
            return false;
        }
        try {
            return Polynomial::fromNode(ExpressionParser::parse((string) $a['expected'], $vars))
                ->equals(Polynomial::fromNode(ExpressionParser::parse((string) $b['expected'], $vars)));
        } catch (MathInputError) {
            return false;
        }
    }
}
