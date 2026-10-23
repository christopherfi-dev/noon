<?php
declare(strict_types=1);

namespace AlgebraEngine\Tutor;

use AlgebraEngine\Math\Ast\BinOp;
use AlgebraEngine\Math\Ast\Group;
use AlgebraEngine\Math\Ast\Neg;
use AlgebraEngine\Math\Ast\Node;
use AlgebraEngine\Math\Ast\Num;
use AlgebraEngine\Math\Ast\Pow;
use AlgebraEngine\Math\EquationParser;
use AlgebraEngine\Math\ExpressionParser;
use AlgebraEngine\Math\FormInspector;
use AlgebraEngine\Math\LinearEquation;
use AlgebraEngine\Math\MathInputError;
use AlgebraEngine\Math\Normalizer;
use AlgebraEngine\Math\Polynomial;
use AlgebraEngine\Math\Rational;

/**
 * Round 6 §4.2: code computes the answer of every typed-answer question from
 * a machine-readable spec. Four kinds:
 *   linear_equation    a linear equation in x with one solution ("3(x + 4) = 21")
 *   expand             an expression to expand or simplify ("7(x + 5)", "x + x + x")
 *   signed_arithmetic  one operation on two integers ("-6 × 7")
 *   numeric_step       a short numeric sub-step ("7 * 5", "21 / 3 - 4")
 * Parsing uses the bounded parsers only (no eval); anything else fails closed.
 */
final class SpecMath
{
    public const KINDS = ['linear_equation', 'expand', 'signed_arithmetic', 'numeric_step'];
    /** The configured range (config keys spec_number_limit, spec_answer_limit, spec_denominator_limit). */
    public const LIMITS = ['number' => 200, 'answer' => 1000, 'denominator' => 12];
    public const MAX_LENGTH = 120;
    private const MAX_STEP_OPERATIONS = 4;

    /**
     * @param array{number?:int,answer?:int,denominator?:int} $limits
     * @return array{kind:string,canonical:string,display:string,response_type:string,variables:list<string>,
     *   answer:array{kind:string,expected:string,requested_form:string},nodes:list<Node>,value:?Rational}
     */
    public static function compute(string $kind, string $expression, array $limits = []): array
    {
        $lim = $limits + self::LIMITS;
        if (!in_array($kind, self::KINDS, true)) {
            throw new SpecError('UNKNOWN_KIND', 'spec.kind must be one of ' . implode(', ', self::KINDS));
        }
        $expression = trim($expression);
        if ($expression === '' || mb_strlen($expression) > self::MAX_LENGTH) {
            throw new SpecError('UNREADABLE', 'spec.expression must be 1 to ' . self::MAX_LENGTH . ' characters');
        }
        try {
            return match ($kind) {
                'linear_equation' => self::linear($expression, $lim),
                'expand' => self::expand($expression, $lim),
                'signed_arithmetic' => self::arithmetic($expression, $lim),
                'numeric_step' => self::step($expression, $lim),
            };
        } catch (MathInputError $e) {
            throw new SpecError('UNREADABLE', 'spec.expression could not be read as a ' . str_replace('_', ' ', $kind) . ' (' . $e->errorCode . ')');
        }
    }

    private static function linear(string $expression, array $lim): array
    {
        [$l, $r] = EquationParser::parse($expression, ['x']);
        self::checkLiterals([$l, $r], $lim);
        $sol = LinearEquation::solve($l, $r);
        if ($sol->kind !== 'UNIQUE' || $sol->value === null) {
            throw new SpecError('NO_UNIQUE_SOLUTION', 'the equation must have exactly one solution for x (it has ' . ($sol->kind === 'ALL' ? 'every number as a solution' : 'no solution') . ')');
        }
        self::checkAnswer($sol->value, $lim);
        return self::out('linear_equation', $expression, MathFormat::equation([$l, $r]), 'scalar', ['x'],
            ['kind' => 'exact_rational', 'expected' => $sol->value->toString(), 'requested_form' => 'value_of_x'], [$l, $r], $sol->value);
    }

    private static function expand(string $expression, array $lim): array
    {
        $node = ExpressionParser::parse($expression, ['x', 'y']);
        self::checkLiterals([$node], $lim);
        $poly = Polynomial::fromNode($node);
        if ($poly->variables() === [] || $poly->degree() > 2) {
            throw new SpecError('OUT_OF_RANGE', 'an expression to expand must use x (and y) and have degree 1 or 2');
        }
        if (FormInspector::expandedSimplified($node) === null) {
            throw new SpecError('NOTHING_TO_DO', 'the expression is already expanded and simplified, so there is nothing to expand');
        }
        foreach (array_merge([''], $poly->variables()) as $k) {
            $c = $poly->coefficientOf($k);
            if (abs($c->num()) > $lim['answer'] || $c->den() > $lim['denominator']) {
                throw new SpecError('OUT_OF_RANGE', 'the expanded answer has a coefficient outside the configured range');
            }
        }
        return self::out('expand', $expression, MathFormat::node($node), 'expression', $poly->variables(),
            ['kind' => 'polynomial', 'expected' => $poly->toString(), 'requested_form' => 'expanded'], [$node], null);
    }

    private static function arithmetic(string $expression, array $lim): array
    {
        $node = ExpressionParser::parse($expression, []);
        self::checkLiterals([$node], $lim);
        $core = $node instanceof Group ? $node->inner : $node;
        if (!($core instanceof BinOp) || PatternMatcher::int($core->left) === null || PatternMatcher::int($core->right) === null) {
            throw new SpecError('NOT_THIS_KIND', 'signed arithmetic is one operation (+, -, *, /) on two integers');
        }
        $value = Polynomial::fromNode($node)->constantValue();
        if ($value === null || !$value->isInteger()) {
            throw new SpecError('OUT_OF_RANGE', 'signed arithmetic must have a whole-number answer');
        }
        self::checkAnswer($value, $lim);
        return self::out('signed_arithmetic', $expression, MathFormat::node($node), 'scalar', [],
            ['kind' => 'exact_rational', 'expected' => $value->toString(), 'requested_form' => 'single_number'], [$node], $value);
    }

    private static function step(string $expression, array $lim): array
    {
        $node = ExpressionParser::parse($expression, []);
        self::checkLiterals([$node], $lim);
        $ops = self::operations($node);
        if ($ops === 0 || $ops > self::MAX_STEP_OPERATIONS) {
            throw new SpecError('NOT_THIS_KIND', 'a numeric step has 1 to ' . self::MAX_STEP_OPERATIONS . ' operations on numbers');
        }
        $value = Polynomial::fromNode($node)->constantValue();
        if ($value === null) {
            throw new SpecError('NOT_THIS_KIND', 'a numeric step uses numbers only');
        }
        self::checkAnswer($value, $lim);
        return self::out('numeric_step', $expression, MathFormat::node($node), 'scalar', [],
            ['kind' => 'exact_rational', 'expected' => $value->toString(), 'requested_form' => 'single_number'], [$node], $value);
    }

    private static function out(string $kind, string $expression, string $display, string $type, array $vars, array $answer, array $nodes, ?Rational $value): array
    {
        return [
            'kind' => $kind,
            'canonical' => strtolower(str_replace(' ', '', Normalizer::normalize($expression))),
            'display' => $display,
            'response_type' => $type,
            'variables' => $vars,
            'answer' => $answer,
            'nodes' => $nodes,
            'value' => $value,
        ];
    }

    /** Every number written in the spec is a whole number within the configured range. @param list<Node> $nodes */
    private static function checkLiterals(array $nodes, array $lim): void
    {
        foreach ($nodes as $n) {
            foreach (self::literals($n) as $v) {
                if (!$v->isInteger()) {
                    throw new SpecError('OUT_OF_RANGE', 'numbers in a spec must be whole numbers');
                }
                if (abs($v->num()) > $lim['number']) {
                    throw new SpecError('OUT_OF_RANGE', 'numbers in a spec must be between -' . $lim['number'] . ' and ' . $lim['number']);
                }
            }
        }
    }

    private static function checkAnswer(Rational $v, array $lim): void
    {
        if (abs($v->num()) > $lim['answer'] || $v->den() > $lim['denominator']) {
            throw new SpecError('OUT_OF_RANGE', 'the answer is outside the configured range (numerator within ' . $lim['answer'] . ', denominator at most ' . $lim['denominator'] . ')');
        }
    }

    /** @return list<Rational> */
    public static function literals(Node $n): array
    {
        if ($n instanceof Num) {
            return [$n->value];
        }
        if ($n instanceof Group) {
            return self::literals($n->inner);
        }
        if ($n instanceof Neg) {
            return self::literals($n->operand);
        }
        if ($n instanceof Pow) {
            return self::literals($n->base);
        }
        if ($n instanceof BinOp) {
            return array_merge(self::literals($n->left), self::literals($n->right));
        }
        return [];
    }

    private static function operations(Node $n): int
    {
        if ($n instanceof Group) {
            return self::operations($n->inner);
        }
        if ($n instanceof Neg) {
            return self::operations($n->operand);
        }
        if ($n instanceof Pow) {
            return 1 + self::operations($n->base);
        }
        if ($n instanceof BinOp) {
            return 1 + self::operations($n->left) + self::operations($n->right);
        }
        return 0;
    }
}
