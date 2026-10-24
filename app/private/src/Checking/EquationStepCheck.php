<?php
declare(strict_types=1);

namespace AlgebraEngine\Checking;

use AlgebraEngine\Math\EquationParser;
use AlgebraEngine\Math\ExpressionParser;
use AlgebraEngine\Math\LinearEquation;
use AlgebraEngine\Math\MathInputError;
use AlgebraEngine\Math\Normalizer;
use AlgebraEngine\Math\Polynomial;

/**
 * Round 9 §4.5: the checker for an equation_step problem (the equation after
 * one step, practice only). Three verdicts:
 *   STEP_CORRECT         each side of the entry equals the expected side as a
 *                        polynomial with exact rationals; the sides may be
 *                        swapped and unsimplified forms are accepted;
 *   EQUIVALENT_NOT_STEP  the entry has the same solution set as the starting
 *                        equation but its sides do not match (the unchanged
 *                        equation, a skipped-ahead "x = 3", another operation);
 *                        recorded as not yet correct, with that observation;
 *   NOT_EQUIVALENT       anything else.
 * An entry that is not an equation is unscorable: a request to rewrite it,
 * never a wrong answer.
 */
final class EquationStepCheck
{
    public const STEP_CORRECT = 'STEP_CORRECT';
    public const EQUIVALENT_NOT_STEP = 'EQUIVALENT_NOT_STEP';
    public const NOT_EQUIVALENT = 'NOT_EQUIVALENT';
    public const SCOPE = 'the equation after one step on both sides: each side compared as a polynomial with exact rationals, sides in either order; otherwise the solution set compared with the starting equation';

    /** @param array{expected:string,start:string} $answer */
    public static function check(array $answer, string $text): CheckOutcome
    {
        $sides = self::sides($text);
        if ($sides === null) {
            return new CheckOutcome('UNSCORABLE', 'not an equation', self::SCOPE, 'Write the equation you get, with an equals sign.');
        }
        [$el, $er] = self::expected((string) $answer['expected']);
        [$l, $r] = $sides;
        $normalized = $l->toString() . ' = ' . $r->toString();
        if (($l->equals($el) && $r->equals($er)) || ($l->equals($er) && $r->equals($el))) {
            return new CheckOutcome('CORRECT', self::STEP_CORRECT, self::SCOPE, null, $normalized);
        }
        return new CheckOutcome('INCORRECT', self::sameSolution($text, (string) $answer['start']) ? self::EQUIVALENT_NOT_STEP : self::NOT_EQUIVALENT, self::SCOPE, null, $normalized);
    }

    /** The verdict class of an outcome (STEP_CORRECT, EQUIVALENT_NOT_STEP or NOT_EQUIVALENT), or null when unscorable. */
    public static function verdictClass(CheckOutcome $o): ?string
    {
        return in_array($o->observation, [self::STEP_CORRECT, self::EQUIVALENT_NOT_STEP, self::NOT_EQUIVALENT], true) ? $o->observation : null;
    }

    /** @return array{0:Polynomial,1:Polynomial}|null */
    public static function sides(string $text): ?array
    {
        $t = Normalizer::normalize($text);
        if (substr_count($t, '=') !== 1) {
            return null;
        }
        try {
            [$l, $r] = EquationParser::parse($t, ['x']);
            return [Polynomial::fromNode($l), Polynomial::fromNode($r)];
        } catch (MathInputError) {
            return null;
        }
    }

    /** @return array{0:Polynomial,1:Polynomial} */
    private static function expected(string $expected): array
    {
        [$l, $r] = array_map('trim', explode('=', $expected, 2));
        return [Polynomial::fromNode(ExpressionParser::parse($l, ['x'])), Polynomial::fromNode(ExpressionParser::parse($r, ['x']))];
    }

    private static function sameSolution(string $text, string $start): bool
    {
        try {
            [$l, $r] = EquationParser::parse(Normalizer::normalize($text), ['x']);
            [$sl, $sr] = EquationParser::parse($start, ['x']);
            $a = LinearEquation::solve($l, $r);
            $b = LinearEquation::solve($sl, $sr);
        } catch (MathInputError) {
            return false;
        }
        return $a->kind === 'UNIQUE' && $b->kind === 'UNIQUE' && $a->value !== null && $b->value !== null && $a->value->equals($b->value);
    }
}
