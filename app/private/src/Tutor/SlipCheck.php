<?php
declare(strict_types=1);

namespace AlgebraEngine\Tutor;

use AlgebraEngine\Checking\CheckOutcome;
use AlgebraEngine\Math\ExpressionParser;
use AlgebraEngine\Math\MathInputError;
use AlgebraEngine\Math\Polynomial;
use AlgebraEngine\Math\Rational;
use AlgebraEngine\Math\ScalarParser;

/**
 * Round 9 §4.4: code decides whether a wrong answer is eligible for the slip
 * pause (SLIP.eligible_for_pause).
 *
 *   Expansions (POSITIVE_DISTRIBUTION, DISTRIBUTION_TRANSFER): every term but
 *   the constant matches the answer, and the wrong constant is a times-table
 *   neighbour of the product a × b (a × (b ± 1) or (a ± 1) × b) or differs
 *   from it in exactly one digit by exactly 1, and is none of a + b, b, a or
 *   −(a × b). For 7(x + 5): 7x + 25 and 7x + 30 are eligible; 7x + 95,
 *   7x + 12 and 7x − 35 are not. A product with a factor of 1 is never one
 *   (round 6 rule, kept).
 *   Linear equations: a bare value of x is never eligible; the arithmetic
 *   signatures stay observations.
 *   Signed multiplication and division: the right sign and a times-table
 *   neighbour of the right size (unchanged).
 *
 * The detail sent to the tutor states what the answer did, never why. With
 * each eligible slip code names the sub-step, as a numeric_step spec the AI
 * may use for its micro-question.
 */
final class SlipCheck
{
    public const EXPANSION = 'The other terms match; the constant {got} is a near miss of {a} × {b} = {product}.';
    public const MULTIPLICATION = 'The sign matches; the size is a near miss of {a} × {b} = {product}.';
    public const DIVISION = 'The sign matches; the size is a near miss of {a} ÷ {b} = {quotient}.';

    /**
     * @param list<array{code:string}> $signatures
     * @return array{code:string,detail:string,micro:array{kind:string,expression:string,answer:string}}|null
     */
    public static function eligible(array $problem, CheckOutcome $o, array $signatures): ?array
    {
        if ($o->verdict !== 'INCORRECT' || $o->normalizedResponse === null || $problem['params'] === null) {
            return null;
        }
        $codes = array_column($signatures, 'code');
        try {
            switch ($problem['template_id']) {
                case 'POSITIVE_DISTRIBUTION':
                case 'DISTRIBUTION_TRANSFER':
                    return self::expansionSlip($problem, $o->normalizedResponse);
                case 'SIGNED_MULTIPLY':
                case 'SIGNED_DIVIDE':
                    return in_array('MAGNITUDE', $codes, true) ? self::factSlip($problem['template_id'], $problem['params'], ScalarParser::parse($o->normalizedResponse)) : null;
            }
        } catch (MathInputError) {
            return null;
        }
        return null;
    }

    private static function expansionSlip(array $problem, string $normalized): ?array
    {
        $vars = $problem['variables'] ?: ['x'];
        $r = Polynomial::fromNode(ExpressionParser::parse($normalized, $vars));
        $key = Polynomial::fromNode(ExpressionParser::parse((string) $problem['answer']['expected'], $vars));
        if ($r->degree() > 1) {
            return null;
        }
        foreach ($vars as $v) {
            if (!$r->coefficientOf($v)->equals($key->coefficientOf($v))) {
                return null; // a term other than the constant differs
            }
        }
        $p = $problem['params'];
        [$a, $b] = $problem['template_id'] === 'POSITIVE_DISTRIBUTION' ? [$p['a'], $p['b']] : [$p['a'], $p['q']];
        $c = $r->coefficientOf('');
        $product = $a * $b;
        if (!$c->isInteger() || abs($a) === 1 || abs($b) === 1) {
            return null;
        }
        $got = $c->num();
        if ($got === $product || in_array($got, [$a + $b, $b, $a, -$product], true)) {
            return null;
        }
        if (!in_array($got, [$a * ($b + 1), $a * ($b - 1), ($a + 1) * $b, ($a - 1) * $b], true) && !self::oneDigitOff($got, $product)) {
            return null;
        }
        $detail = strtr(self::EXPANSION, ['{got}' => (string) $got, '{a}' => (string) $a, '{b}' => (string) $b, '{product}' => (string) $product]);
        return self::slip('PRODUCT_FACT', $detail, $a . ' * ' . $b, $product);
    }

    /** Same sign and number of digits, and exactly one digit differs, by exactly 1. */
    public static function oneDigitOff(int $got, int $product): bool
    {
        if (($got < 0) !== ($product < 0)) {
            return false;
        }
        $g = (string) abs($got);
        $p = (string) abs($product);
        if (strlen($g) !== strlen($p)) {
            return false;
        }
        $differing = 0;
        for ($i = 0; $i < strlen($g); $i++) {
            if ($g[$i] !== $p[$i]) {
                if (abs((int) $g[$i] - (int) $p[$i]) !== 1) {
                    return false;
                }
                $differing++;
            }
        }
        return $differing === 1;
    }

    /** N05/N06: the right sign, and a times-table neighbour of the right size. */
    private static function factSlip(string $templateId, array $p, Rational $r): ?array
    {
        $a = abs($p['a']);
        $b = abs($p['b']);
        $got = abs($r->num());
        if (!$r->isInteger()) {
            return null;
        }
        if ($templateId === 'SIGNED_MULTIPLY') {
            $near = in_array($got, [$a * ($b + 1), $a * ($b - 1), ($a + 1) * $b, ($a - 1) * $b], true);
            return $near ? self::slip('MAGNITUDE', strtr(self::MULTIPLICATION, ['{a}' => (string) $a, '{b}' => (string) $b, '{product}' => (string) ($a * $b)]), $a . ' * ' . $b, $a * $b) : null;
        }
        $q = intdiv($a, $b);
        return in_array($got, [$q + 1, $q - 1], true)
            ? self::slip('MAGNITUDE', strtr(self::DIVISION, ['{a}' => (string) $a, '{b}' => (string) $b, '{quotient}' => (string) $q]), $a . ' / ' . $b, $q) : null;
    }

    private static function slip(string $code, string $detail, string $micro, int $answer): array
    {
        return ['code' => $code, 'detail' => $detail, 'micro' => ['kind' => 'numeric_step', 'expression' => $micro, 'answer' => (string) $answer]];
    }
}
