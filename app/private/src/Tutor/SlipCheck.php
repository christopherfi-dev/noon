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
 * Round 6 §4.4: code confirms a slip only when the recipe's slip signature
 * matches — the structure is correct and there is one lower-level arithmetic
 * error (7x + 25 for 7(x + 5)). A slip the structure contradicts (9x + 8 for
 * 9(x + 8): only the x was multiplied) is never confirmed.
 *
 * With each confirmed slip code names the sub-step that went wrong, as a
 * numeric_step spec the AI may use for its micro-question.
 */
final class SlipCheck
{
    /**
     * @param list<array{code:string}> $signatures
     * @return array{code:string,detail:string,micro:array{kind:string,expression:string,answer:string}}|null
     */
    public static function confirm(array $problem, CheckOutcome $o, array $signatures): ?array
    {
        if ($o->verdict !== 'INCORRECT' || $o->normalizedResponse === null || $problem['params'] === null) {
            return null;
        }
        $codes = array_column($signatures, 'code');
        $p = $problem['params'];
        try {
            switch ($problem['template_id']) {
                case 'POSITIVE_DISTRIBUTION':
                case 'DISTRIBUTION_TRANSFER':
                    return in_array('PRODUCT_FACT', $codes, true) ? self::productSlip($problem, $o->normalizedResponse) : null;
                case 'GROUPED_LINEAR':
                case 'GROUPED_LINEAR_TRANSFER':
                    $code = in_array('ARITH_FINAL_PLUS', $codes, true) ? 'ARITH_FINAL_PLUS' : (in_array('ARITH_FINAL_MINUS', $codes, true) ? 'ARITH_FINAL_MINUS' : null);
                    if ($code === null) {
                        return null;
                    }
                    $quotient = intdiv($p['c'] - ($p['d'] ?? 0), $p['a']);
                    return self::slip($code, 'The steps were right; the last subtraction was off by one.', $quotient . ' - (' . $p['b'] . ')', $quotient - $p['b']);
                case 'TWO_STEP':
                    if (!in_array('ARITH_OFF_BY_ONE', $codes, true)) {
                        return null;
                    }
                    return self::slip('ARITH_OFF_BY_ONE', 'The steps were right; the last division was off by one.', ($p['c'] - $p['b']) . ' / ' . $p['a'], intdiv($p['c'] - $p['b'], $p['a']));
                case 'SIGNED_MULTIPLY':
                case 'SIGNED_DIVIDE':
                    return in_array('MAGNITUDE', $codes, true) ? self::factSlip($problem['template_id'], $p, ScalarParser::parse($o->normalizedResponse)) : null;
            }
        } catch (MathInputError) {
            return null;
        }
        return null;
    }

    /** E06: same terms as the answer, exactly one coefficient wrong, and it is a product the student had to work out. */
    private static function productSlip(array $problem, string $normalized): ?array
    {
        $vars = $problem['variables'] ?: ['x'];
        $r = Polynomial::fromNode(ExpressionParser::parse($normalized, $vars));
        $key = Polynomial::fromNode(ExpressionParser::parse((string) $problem['answer']['expected'], $vars));
        $p = $problem['params'];
        // Coefficients that are products the student computed: monomial key => [factor, factor].
        $products = match ($problem['template_id']) {
            'POSITIVE_DISTRIBUTION' => ['' => [$p['a'], $p['b']]],
            default => $p['form'] === 'COEFF_INSIDE' ? ['x' => [$p['a'], $p['p']], '' => [$p['a'], $p['q']]] : ['' => [$p['a'], $p['q']]],
        };
        $wrong = [];
        foreach (array_merge([''], $vars) as $k) {
            if (!$r->coefficientOf($k)->equals($key->coefficientOf($k))) {
                $wrong[] = $k;
            }
        }
        if (count($wrong) !== 1 || !isset($products[$wrong[0]])) {
            return null;
        }
        [$f1, $f2] = $products[$wrong[0]];
        return self::slip('PRODUCT_FACT', 'The expansion was set out correctly; one multiplication (' . $f1 . ' × ' . $f2 . ') went wrong.', $f1 . ' * ' . $f2, $f1 * $f2);
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
            return $near ? self::slip('MAGNITUDE', 'The sign was right; one multiplication fact (' . $a . ' × ' . $b . ') went wrong.', $a . ' * ' . $b, $a * $b) : null;
        }
        $q = intdiv($a, $b);
        return in_array($got, [$q + 1, $q - 1], true)
            ? self::slip('MAGNITUDE', 'The sign was right; one division fact (' . $a . ' ÷ ' . $b . ') went wrong.', $a . ' / ' . $b, $q) : null;
    }

    private static function slip(string $code, string $detail, string $micro, int $answer): array
    {
        return ['code' => $code, 'detail' => $detail, 'micro' => ['kind' => 'numeric_step', 'expression' => $micro, 'answer' => (string) $answer]];
    }
}
