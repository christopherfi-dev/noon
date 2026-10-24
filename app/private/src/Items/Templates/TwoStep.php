<?php
declare(strict_types=1);

namespace AlgebraEngine\Items\Templates;

use AlgebraEngine\Checking\CheckOutcome;
use AlgebraEngine\Items\AbstractTemplate;
use AlgebraEngine\Items\Fmt;
use AlgebraEngine\Support\RandomSource;

/** Q06: a·x + b = c with an integer solution s. */
final class TwoStep extends AbstractTemplate
{
    public function id(): string { return 'TWO_STEP'; }
    public function skillId(): string { return 'Q06'; }
    public function componentId(): string { return 'numeric_solution'; }
    public function responseType(): string { return 'scalar'; }

    public function parameterSpec(): array
    {
        return [
            'a' => 'integer -9..9, not -1, 0, 1',
            'b' => 'integer -15..15, not 0',
            'c' => 'integer, c = a*s + b with integer solution s in -10..10',
        ];
    }

    public function generate(RandomSource $rng, ?array $avoid = null): array
    {
        return $this->sample(function () use ($rng) {
            $a = $rng->int(-9, 9);
            $b = $rng->int(-15, 15);
            return ['a' => $a, 'b' => $b, 'c' => $a * $rng->int(-10, 10) + $b];
        }, $avoid);
    }

    public function validate(array $p): array
    {
        $e = [];
        self::onlyKeys($p, ['a', 'b', 'c'], $e);
        $a = self::needInt($p, 'a', -9, 9, $e, [-1, 0, 1]);
        $b = self::needInt($p, 'b', -15, 15, $e, [0]);
        $c = self::needInt($p, 'c', -120, 120, $e);
        if ($a !== null && $b !== null && $c !== null) {
            if (($c - $b) % $a !== 0) {
                $e[] = 'c - b must be an exact multiple of a';
            } else {
                $s = intdiv($c - $b, $a);
                if (abs($s) > 10) {
                    $e[] = 'solution must be in -10..10';
                }
                $values = [self::r($s), self::r($c + $b, $a), self::r($c, $a)->sub(self::r($b)), self::r(($c - $b) * $a), self::r($s + 1), self::r($s - 1)];
                if ($s !== 0) {
                    $values[] = self::r(-$s);
                }
                if (!self::distinct($values)) {
                    $e[] = 'error patterns must give distinct values';
                }
            }
        }
        return $e;
    }

    public function render(array $p): string
    {
        return 'Solve ' . $this->display($p) . ' for x.';
    }

    public function display(array $p): string
    {
        return Fmt::plus(Fmt::cx($p['a']), $p['b']) . ' = ' . Fmt::n($p['c']);
    }

    public function answerSpec(array $p): array
    {
        return $this->scalarSpec(self::r(intdiv($p['c'] - $p['b'], $p['a'])), 'value_of_x');
    }

    public function mathematicalObjects(array $p): array
    {
        return ['equation' => '(' . $p['a'] . ')*x+(' . $p['b'] . ')=' . $p['c'], 'display' => $this->display($p), 'variable' => 'x'];
    }

    public function matchSignatures(array $p, string $response, CheckOutcome $outcome): array
    {
        [$a, $b, $c] = [$p['a'], $p['b'], $p['c']];
        $s = intdiv($c - $b, $a);
        $sigs = [
            ['ADD_INSTEAD_OF_SUBTRACT', 'matches adding the constant instead of undoing it', 'Q04', self::r($c + $b, $a)],
            ['DIVIDE_FIRST_ONLY', 'matches dividing only the right side by the coefficient before removing the constant', null, self::r($c, $a)->sub(self::r($b))],
            ['MULTIPLY_INSTEAD_OF_DIVIDE', 'matches multiplying by the coefficient instead of dividing', 'Q05', self::r(($c - $b) * $a)],
            ['ARITH_OFF_BY_ONE', 'matches the solution plus or minus one', null, fn ($r) => $r->equals(self::r($s + 1)) || $r->equals(self::r($s - 1))],
        ];
        if ($s !== 0) {
            $sigs[] = ['QUOTIENT_SIGN', 'matches the correct size with the opposite sign', 'Q05', self::r(-$s)];
        }
        return $this->matchScalar($outcome, $sigs);
    }

    public function signatureFeedback(array $p, string $code): ?string
    {
        return match ($code) {
            'ADD_INSTEAD_OF_SUBTRACT' => 'Your answer is what you get by adding ' . Fmt::abs($p['b']) . ' on the right side when it needed to be undone.',
            'QUOTIENT_SIGN' => 'Your answer has the right size but the opposite sign.',
            default => null,
        };
    }
}
