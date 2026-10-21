<?php
declare(strict_types=1);

namespace AlgebraEngine\Items\Templates;

use AlgebraEngine\Checking\CheckOutcome;
use AlgebraEngine\Items\AbstractTemplate;
use AlgebraEngine\Items\Fmt;
use AlgebraEngine\Support\RandomSource;

/** Q05: a·x = b with an integer solution. */
final class OneStepMultiply extends AbstractTemplate
{
    public function id(): string { return 'ONE_STEP_MULTIPLY'; }
    public function skillId(): string { return 'Q05'; }
    public function componentId(): string { return 'numeric_solution'; }
    public function responseType(): string { return 'scalar'; }

    public function parameterSpec(): array
    {
        return ['a' => 'integer -12..12, not -1, 0, 1', 'b' => 'integer, b = a*s with integer solution s in -12..12, s not 0, -1, 1'];
    }

    public function generate(RandomSource $rng, ?array $avoid = null): array
    {
        return $this->sample(function () use ($rng) {
            $a = $rng->int(-12, 12);
            return ['a' => $a, 'b' => $a * $rng->int(-12, 12)];
        }, $avoid);
    }

    public function validate(array $p): array
    {
        $e = [];
        self::onlyKeys($p, ['a', 'b'], $e);
        $a = self::needInt($p, 'a', -12, 12, $e, [-1, 0, 1]);
        $b = self::needInt($p, 'b', -144, 144, $e, [0]);
        if ($a !== null && $b !== null) {
            if ($b % $a !== 0) {
                $e[] = 'b must be an exact multiple of a';
            } else {
                $s = intdiv($b, $a);
                if (abs($s) > 12 || abs($s) === 1) {
                    $e[] = 'solution must be in -12..12 and not -1 or 1';
                }
                if (!self::distinct([self::r($s), self::r(-$s), self::r($a * $b), self::r($b - $a)])) {
                    $e[] = 'error patterns must give distinct values';
                }
            }
        }
        return $e;
    }

    public function render(array $p): string
    {
        return 'Solve ' . Fmt::cx($p['a']) . ' = ' . Fmt::n($p['b']) . ' for x.';
    }

    public function answerSpec(array $p): array
    {
        return $this->scalarSpec(self::r(intdiv($p['b'], $p['a'])), 'value_of_x');
    }

    public function mathematicalObjects(array $p): array
    {
        return ['equation' => '(' . $p['a'] . ')*x=' . $p['b'], 'display' => Fmt::cx($p['a']) . ' = ' . Fmt::n($p['b']), 'variable' => 'x'];
    }

    public function matchSignatures(array $p, string $response, CheckOutcome $outcome): array
    {
        $s = intdiv($p['b'], $p['a']);
        return $this->matchScalar($outcome, [
            ['QUOTIENT_SIGN', 'matches the correct size with the opposite sign (sign of a quotient)', 'N06', self::r(-$s)],
            ['MULTIPLIED', 'matches multiplying by the coefficient instead of dividing', 'Q03', self::r($p['a'] * $p['b'])],
            ['RECIPROCAL', 'matches dividing the coefficient by the constant', 'Q03', self::r($p['a'], $p['b'])],
            ['SUBTRACTED', 'matches subtracting the coefficient instead of dividing', 'Q03', self::r($p['b'] - $p['a'])],
        ]);
    }

    public function signatureFeedback(array $p, string $code): ?string
    {
        return match ($code) {
            'QUOTIENT_SIGN' => 'Your answer has the right size but the opposite sign.',
            'MULTIPLIED' => 'Your answer is what you get by multiplying ' . Fmt::n($p['b']) . ' by ' . Fmt::p($p['a']) . '.',
            default => null,
        };
    }
}
