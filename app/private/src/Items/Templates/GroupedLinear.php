<?php
declare(strict_types=1);

namespace AlgebraEngine\Items\Templates;

use AlgebraEngine\Checking\CheckOutcome;
use AlgebraEngine\Items\AbstractTemplate;
use AlgebraEngine\Items\Fmt;
use AlgebraEngine\Math\Rational;
use AlgebraEngine\Support\RandomSource;

/**
 * Q07 constructed: a(x + b) = c with an integer solution s = c/a − b.
 * The host computes the answers that known error patterns produce; a match
 * is recorded as an observation and selects the probe/descent branch.
 */
final class GroupedLinear extends AbstractTemplate
{
    public function id(): string { return 'GROUPED_LINEAR'; }
    public function skillId(): string { return 'Q07'; }
    public function componentId(): string { return 'numeric_solution'; }
    public function responseType(): string { return 'scalar'; }

    public function parameterSpec(): array
    {
        return [
            'a' => 'integer 2..9 (factor outside the parentheses)',
            'b' => 'integer -9..9, not 0 (constant inside the parentheses)',
            'c' => 'integer right side, c = a*(s + b) for an integer solution s in -9..12, c != 0; the error-pattern answers must all differ',
        ];
    }

    public function generate(RandomSource $rng, ?array $avoid = null): array
    {
        return $this->sample(function () use ($rng) {
            $a = $rng->int(2, 9);
            $b = $rng->int(-9, 9);
            return ['a' => $a, 'b' => $b, 'c' => $a * ($rng->int(-9, 12) + $b)];
        }, $avoid);
    }

    public function validate(array $p): array
    {
        $e = [];
        self::onlyKeys($p, ['a', 'b', 'c'], $e);
        $a = self::needInt($p, 'a', 2, 9, $e);
        $b = self::needInt($p, 'b', -9, 9, $e, [0]);
        $c = self::needInt($p, 'c', -200, 200, $e, [0]);
        if ($a !== null && $b !== null && $c !== null) {
            $e = array_merge($e, self::checkGrouped($a, $b, $c));
        }
        return $e;
    }

    /** @return list<string> */
    public static function checkGrouped(int $a, int $b, int $c): array
    {
        if ($c % $a !== 0) {
            return ['c must be an exact multiple of a so the solution is an integer'];
        }
        $s = intdiv($c, $a) - $b;
        if ($s < -9 || $s > 12) {
            return ['the solution c/a - b must be between -9 and 12'];
        }
        if (!self::distinct(self::uniqueByCode(self::signatureValues($a, $b, $c), $s))) {
            return ['the error-pattern answers must differ from each other and from the solution'];
        }
        return [];
    }

    /** @return list<array{0:string,1:Rational}> */
    public static function signatureValues(int $a, int $b, int $c): array
    {
        $s = intdiv($c, $a) - $b;
        $signB = Rational::of($c, $a)->add(Rational::fromInt($b));
        return [
            // Round 7 §8.3: one code and one description for this mistake on Q07 and E06.
            ['FIRST_TERM_ONLY', Rational::of($c - $b, $a)],
            ['SIGN_ERROR_B', $signB],
            ['DIVIDE_THEN_ADD_B', $signB],
            ['ARITH_FINAL_PLUS', Rational::fromInt($s + 1)],
            ['ARITH_FINAL_MINUS', Rational::fromInt($s - 1)],
        ];
    }

    /** Distinct values that must not collide: the two sign-error codes share a value by construction. */
    private static function uniqueByCode(array $sigs, int $s): array
    {
        $vals = [Rational::fromInt($s)];
        foreach ($sigs as [$code, $v]) {
            if ($code !== 'DIVIDE_THEN_ADD_B') {
                $vals[] = $v;
            }
        }
        return $vals;
    }

    public function display(array $p): string
    {
        return $p['a'] . '(' . Fmt::plus('x', $p['b']) . ') = ' . Fmt::n($p['c']);
    }

    public function render(array $p): string
    {
        return 'Solve ' . $this->display($p) . ' for x.';
    }

    public function answerSpec(array $p): array
    {
        return $this->scalarSpec(Rational::fromInt(intdiv($p['c'], $p['a']) - $p['b']), 'value_of_x');
    }

    public function mathematicalObjects(array $p): array
    {
        $sig = [];
        foreach (self::signatureValues($p['a'], $p['b'], $p['c']) as [$code, $v]) {
            $sig[$code] = $v->toString();
        }
        return ['equation' => $p['a'] . '*(x+(' . $p['b'] . '))=' . $p['c'], 'display' => $this->display($p), 'variable' => 'x', 'signature_answers' => $sig];
    }

    public function matchSignatures(array $p, string $response, CheckOutcome $outcome): array
    {
        return self::groupedSignatures($outcome, self::signatureValues($p['a'], $p['b'], $p['c']), fn ($o, $s) => $this->matchScalar($o, $s));
    }

    /** @param list<array{0:string,1:Rational}> $values */
    public static function groupedSignatures(CheckOutcome $outcome, array $values, callable $matcher): array
    {
        $labels = [
            'FIRST_TERM_ONLY' => ['matches distribution-to-first-term-only (the factor multiplied only the first term inside the parentheses)', 'E06'],
            'SIGN_ERROR_B' => ['matches sign-error-on-b', 'Q06'],
            'DIVIDE_THEN_ADD_B' => ['matches dividing-c-by-a-then-adding-b', 'Q06'],
            'ARITH_FINAL_PLUS' => ['matches the solution plus or minus one', 'Q06'],
            'ARITH_FINAL_MINUS' => ['matches the solution plus or minus one', 'Q06'],
        ];
        $sigs = [];
        foreach ($values as [$code, $v]) {
            $sigs[] = [$code, $labels[$code][0], $labels[$code][1], $v];
        }
        return $matcher($outcome, $sigs);
    }

    public function signatureFeedback(array $p, string $code): ?string
    {
        return self::groupedFeedback($p['a'], $p['b'], $code);
    }

    public static function groupedFeedback(int $a, int $b, string $code): ?string
    {
        return match ($code) {
            'FIRST_TERM_ONLY' => DistributionTransfer::firstTermOnly($a),
            'SIGN_ERROR_B', 'DIVIDE_THEN_ADD_B' => 'Your answer moved the ' . Fmt::n($b) . ' to the other side without changing its sign.',
            default => null,
        };
    }
}
