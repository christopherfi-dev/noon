<?php
declare(strict_types=1);

namespace AlgebraEngine\Items;

use AlgebraEngine\Checking\CheckOutcome;
use AlgebraEngine\Math\ExpressionParser;
use AlgebraEngine\Math\MathInputError;
use AlgebraEngine\Math\Polynomial;
use AlgebraEngine\Math\Rational;
use AlgebraEngine\Math\ScalarParser;

/**
 * Round 6 (architecture 1.3, §5): slip signatures. A slip is an answer whose
 * structure shows the skill applied correctly with one lower-level arithmetic
 * error. Each template declares the arithmetic sub-steps of its procedure;
 * for each sub-step the host can tell which wrong result of that sub-step,
 * with every other step done correctly, would give the learner's answer.
 *
 * Two checks use that:
 * - find(): the host's own slip signatures. The wrong sub-step result is a
 *   typical slip: one away from the right result, or one digit different.
 * - structural(): the check a model-proposed slip must pass. The named
 *   sub-step reproduces the answer, the wrong result has the right sign and
 *   is near the right one (for a product, within one row of the times table).
 * Neither applies when a conceptual error pattern matched: its branch stands.
 * A slip is only a candidate until the learner answers the micro-question.
 */
final class SlipCatalog
{
    /** Signature codes that describe arithmetic errors; any other matched code is a conceptual pattern. */
    public const ARITHMETIC_CODES = ['PRODUCT_FACT', 'ARITH_FINAL_PLUS', 'ARITH_FINAL_MINUS', 'ARITH_OFF_BY_ONE', 'MAGNITUDE'];

    /**
     * Arithmetic sub-steps of a template's procedure, in the order a micro-check asks about them.
     *
     * @return list<array{id:string,display:string,value:int,kind:string,factors:?array{0:int,1:int},skill_id:string,wrong:callable(CheckOutcome):?int}>
     */
    public static function subSteps(string $templateId, array $p): array
    {
        $minus = fn (int $x, int $y): string => $y >= 0 ? Fmt::n($x) . ' ' . Fmt::MINUS . ' ' . $y : Fmt::n($x) . ' + ' . (-$y);
        switch ($templateId) {
            case 'POSITIVE_DISTRIBUTION':
                [$a, $b] = [$p['a'], $p['b']];
                return [self::step('product', "$a × $b", $a * $b, 'product', [$a, $b], 'N18',
                    fn (CheckOutcome $o) => self::linearPart($o, ['x'], ['x' => $a], ''))];
            case 'DISTRIBUTION_TRANSFER':
                [$a, $q] = [$p['a'], $p['q']];
                if ($p['form'] === 'COEFF_INSIDE') {
                    $pp = $p['p'];
                    return [
                        self::step('constant', "$a × $q", $a * $q, 'product', [$a, $q], 'N18', fn (CheckOutcome $o) => self::linearPart($o, ['x'], ['x' => $a * $pp], '')),
                        self::step('coefficient', "$a × $pp", $a * $pp, 'product', [$a, $pp], 'N18', fn (CheckOutcome $o) => self::linearPart($o, ['x'], ['' => $a * $q], 'x')),
                    ];
                }
                return [self::step('constant', "$a × $q", $a * $q, 'product', [$a, $q], 'N18', fn (CheckOutcome $o) => self::linearPart($o, ['x', 'y'], ['x' => $a, 'y' => $a], ''))];
            case 'GROUPED_LINEAR':
            case 'GROUPED_LINEAR_TRANSFER':
                [$a, $b] = [$p['a'], $p['b']];
                $d = $templateId === 'GROUPED_LINEAR' ? 0 : $p['d'];
                $e = $p['c'] - $d;
                $q = intdiv($e, $a);
                $steps = [
                    self::step('subtract', $minus($q, $b), $q - $b, $b > 0 ? 'difference' : 'sum', null, 'N03', fn (CheckOutcome $o) => self::intAnswer($o)),
                    self::step('divide', Fmt::n($e) . ' ÷ ' . $a, $q, 'quotient', null, 'N06', fn (CheckOutcome $o) => ($s = self::intAnswer($o)) === null ? null : $s + $b),
                ];
                if ($d !== 0) {
                    $steps[] = self::step('move', $minus($p['c'], $d), $e, $d > 0 ? 'difference' : 'sum', null, 'N03',
                        fn (CheckOutcome $o) => ($s = self::intAnswer($o)) === null ? null : ($s + $b) * $a);
                }
                return $steps;
            case 'TWO_STEP':
                [$a, $b, $c] = [$p['a'], $p['b'], $p['c']];
                $r = $c - $b;
                return [
                    self::step('divide', Fmt::n($r) . ' ÷ ' . Fmt::p($a), intdiv($r, $a), 'quotient', null, 'N06', fn (CheckOutcome $o) => self::intAnswer($o)),
                    self::step('move', $minus($c, $b), $r, $b > 0 ? 'difference' : 'sum', null, 'N03', function (CheckOutcome $o) use ($a) {
                        $s = self::answer($o);
                        if ($s === null) {
                            return null;
                        }
                        $k = $s->mul(Rational::fromInt($a));
                        return $k->isInteger() ? $k->num() : null;
                    }),
                ];
            case 'ONE_STEP_MULTIPLY':
                return [self::step('divide', Fmt::n($p['b']) . ' ÷ ' . Fmt::p($p['a']), intdiv($p['b'], $p['a']), 'quotient', null, 'N06', fn (CheckOutcome $o) => self::intAnswer($o))];
            case 'ONE_STEP_ADD':
                return [self::step('subtract', $minus($p['b'], $p['a']), $p['b'] - $p['a'], $p['a'] > 0 ? 'difference' : 'sum', null, 'N03', fn (CheckOutcome $o) => self::intAnswer($o))];
            case 'SIGNED_MULTIPLY':
                [$a, $b] = [abs($p['a']), abs($p['b'])];
                $sign = ($p['a'] * $p['b']) <=> 0;
                return [self::step('size', "$a × $b", $a * $b, 'product', [$a, $b], 'N18', fn (CheckOutcome $o) => self::sizeWithSign($o, $sign))];
            case 'SIGNED_DIVIDE':
                [$a, $b] = [abs($p['a']), abs($p['b'])];
                $sign = intdiv($p['a'], $p['b']) <=> 0;
                return [self::step('size', "$a ÷ $b", intdiv($a, $b), 'quotient', null, 'N18', fn (CheckOutcome $o) => self::sizeWithSign($o, $sign))];
            case 'SIGNED_ADD':
            case 'SIGNED_SUBTRACT':
                $x = $p['a'];
                $y = $templateId === 'SIGNED_ADD' ? $p['b'] : -$p['b'];
                $sign = ($x + $y) <=> 0;
                [$big, $small] = abs($x) >= abs($y) ? [abs($x), abs($y)] : [abs($y), abs($x)];
                return ($x < 0) === ($y < 0)
                    ? [self::step('size', "$big + $small", $big + $small, 'sum', null, 'N17', fn (CheckOutcome $o) => self::sizeWithSign($o, $sign))]
                    : [self::step('size', $big . ' ' . Fmt::MINUS . ' ' . $small, $big - $small, 'difference', null, 'N17', fn (CheckOutcome $o) => self::sizeWithSign($o, $sign))];
        }
        return [];
    }

    /** True when the template declares slip signatures at all. */
    public static function supports(string $templateId, array $p): bool
    {
        return self::subSteps($templateId, $p) !== [];
    }

    /**
     * The host's slip signatures: the first sub-step whose result, changed to a
     * typical slip value, reproduces the learner's answer.
     *
     * @param list<array{code:string}> $signatures error patterns matched for this answer
     * @return array{sub_step:array{id:string,display:string,value:int,kind:string,skill_id:string},wrong_value:int}|null
     */
    public static function find(string $templateId, array $p, CheckOutcome $outcome, array $signatures): ?array
    {
        if ($outcome->verdict !== 'INCORRECT' || self::conceptual($signatures) !== null) {
            return null;
        }
        foreach (self::subSteps($templateId, $p) as $s) {
            $k = ($s['wrong'])($outcome);
            if ($k !== null && self::typical($k, $s['value'])) {
                return ['sub_step' => self::public($s), 'wrong_value' => $k];
            }
        }
        return null;
    }

    /**
     * The structural check a model-proposed slip must pass.
     *
     * @return array{ok:bool,reason:string,sub_step:?array,wrong_value:?int}
     */
    public static function structural(string $templateId, array $p, CheckOutcome $outcome, array $signatures, string $subStepId): array
    {
        $fail = fn (string $reason) => ['ok' => false, 'reason' => $reason, 'sub_step' => null, 'wrong_value' => null];
        if ($outcome->verdict !== 'INCORRECT') {
            return $fail('only an incorrect answer can be a slip');
        }
        if (($code = self::conceptual($signatures)) !== null) {
            return $fail('the answer matches the error pattern ' . $code . ', which is not an arithmetic slip; its branch stands');
        }
        foreach (self::subSteps($templateId, $p) as $s) {
            if ($s['id'] !== $subStepId) {
                continue;
            }
            $k = ($s['wrong'])($outcome);
            if ($k === null) {
                return $fail('changing only the result of ' . $s['display'] . ' cannot give this answer; the rest of the work is not correct');
            }
            if (!self::near($k, $s)) {
                return $fail('the answer would need ' . $s['display'] . ' = ' . $k . ', which is not a near miss of ' . $s['value'] . ' with the right sign');
            }
            return ['ok' => true, 'reason' => 'structure agrees: one arithmetic error in ' . $s['display'], 'sub_step' => self::public($s), 'wrong_value' => $k];
        }
        return $fail('"' . $subStepId . '" is not a declared sub-step of this item');
    }

    /** First matched conceptual (non-arithmetic) signature code, if any: its branch stands and no slip is considered. */
    public static function conceptual(array $signatures): ?string
    {
        foreach ($signatures as $sig) {
            if (!in_array($sig['code'], self::ARITHMETIC_CODES, true)) {
                return $sig['code'];
            }
        }
        return null;
    }

    /** Host signature: one away, or the same number of digits with exactly one digit different, with the right sign. */
    private static function typical(int $k, int $v): bool
    {
        return $k !== $v && $v !== 0 && ($k <=> 0) === ($v <=> 0) && (abs($k - $v) === 1 || self::oneDigit($k, $v));
    }

    /** Structural check: the right sign, and near the right result (a typical slip, or within one row of a times table for a product, or two for other steps). */
    private static function near(int $k, array $s): bool
    {
        $v = $s['value'];
        if ($k === $v || $v === 0 || ($k <=> 0) !== ($v <=> 0)) {
            return false;
        }
        $limit = $s['kind'] === 'product' ? max(abs($s['factors'][0]), abs($s['factors'][1])) : 2;
        return abs($k - $v) <= $limit || self::oneDigit($k, $v);
    }

    private static function oneDigit(int $k, int $v): bool
    {
        $a = (string) abs($k);
        $b = (string) abs($v);
        if (strlen($a) !== strlen($b)) {
            return false;
        }
        $diff = 0;
        for ($i = 0; $i < strlen($a); $i++) {
            $diff += $a[$i] !== $b[$i] ? 1 : 0;
        }
        return $diff === 1;
    }

    private static function step(string $id, string $display, int $value, string $kind, ?array $factors, string $skill, callable $wrong): array
    {
        return ['id' => $id, 'display' => $display, 'value' => $value, 'kind' => $kind, 'factors' => $factors, 'skill_id' => $skill, 'wrong' => $wrong];
    }

    private static function public(array $s): array
    {
        unset($s['wrong']);
        return $s;
    }

    private static function answer(CheckOutcome $o): ?Rational
    {
        if ($o->normalizedResponse === null) {
            return null;
        }
        try {
            return ScalarParser::parse($o->normalizedResponse);
        } catch (MathInputError) {
            return null;
        }
    }

    private static function intAnswer(CheckOutcome $o): ?int
    {
        $r = self::answer($o);
        return $r !== null && $r->isInteger() ? $r->num() : null;
    }

    /** The size of the answer when its sign is the right one. */
    private static function sizeWithSign(CheckOutcome $o, int $sign): ?int
    {
        $s = self::intAnswer($o);
        return $s === null || ($s <=> 0) !== $sign ? null : abs($s);
    }

    /**
     * For a linear answer: when every coefficient in $fixed is right and the
     * answer has no other term, the coefficient at $free (variable name, or
     * '' for the constant) is the changed sub-step result.
     *
     * @param list<string> $vars
     * @param array<string,int> $fixed
     */
    private static function linearPart(CheckOutcome $o, array $vars, array $fixed, string $free): ?int
    {
        if ($o->normalizedResponse === null) {
            return null;
        }
        try {
            $poly = Polynomial::fromNode(ExpressionParser::parse($o->normalizedResponse, $vars));
        } catch (MathInputError) {
            return null;
        }
        if ($poly->degree() > 1) {
            return null;
        }
        foreach (array_merge([''], $vars) as $term) {
            if ($term === $free) {
                continue;
            }
            $want = $fixed[$term] ?? 0;
            if (!$poly->coefficientOf($term)->equals(Rational::fromInt($want))) {
                return null;
            }
        }
        $k = $poly->coefficientOf($free);
        return $k->isInteger() ? $k->num() : null;
    }
}
