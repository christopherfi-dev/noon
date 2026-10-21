<?php
declare(strict_types=1);

namespace AlgebraEngine\Items\Templates;

use AlgebraEngine\Checking\CheckOutcome;
use AlgebraEngine\Items\AbstractTemplate;
use AlgebraEngine\Items\Fmt;
use AlgebraEngine\Support\RandomSource;

/** Q04: x + a = b (a may be negative, shown as x − |a|). */
final class OneStepAdd extends AbstractTemplate
{
    public function id(): string { return 'ONE_STEP_ADD'; }
    public function skillId(): string { return 'Q04'; }
    public function componentId(): string { return 'numeric_solution'; }
    public function responseType(): string { return 'scalar'; }

    public function parameterSpec(): array
    {
        return ['a' => 'integer -15..15, not 0', 'b' => 'integer -30..30; solution b - a must be nonzero and differ from b + a and from -(b - a)'];
    }

    public function generate(RandomSource $rng, ?array $avoid = null): array
    {
        return $this->sample(function () use ($rng) {
            $a = $rng->int(-15, 15);
            return ['a' => $a, 'b' => $rng->int(-15, 15) + $a];
        }, $avoid);
    }

    public function validate(array $p): array
    {
        $e = [];
        self::onlyKeys($p, ['a', 'b'], $e);
        $a = self::needInt($p, 'a', -15, 15, $e, [0]);
        $b = self::needInt($p, 'b', -30, 30, $e);
        if ($a !== null && $b !== null) {
            $s = $b - $a;
            if ($s === 0 || !self::distinct([self::r($s), self::r($b + $a), self::r(-$s)])) {
                $e[] = 'solution must be nonzero and error patterns must give distinct values';
            }
        }
        return $e;
    }

    public function render(array $p): string
    {
        return 'Solve ' . Fmt::plus('x', $p['a']) . ' = ' . Fmt::n($p['b']) . '. Give the value of x.';
    }

    public function answerSpec(array $p): array
    {
        return $this->scalarSpec(self::r($p['b'] - $p['a']), 'value_of_x');
    }

    public function mathematicalObjects(array $p): array
    {
        return ['equation' => 'x+(' . $p['a'] . ')=' . $p['b'], 'display' => Fmt::plus('x', $p['a']) . ' = ' . Fmt::n($p['b']), 'variable' => 'x'];
    }

    public function matchSignatures(array $p, string $response, CheckOutcome $outcome): array
    {
        $s = $p['b'] - $p['a'];
        return $this->matchScalar($outcome, [
            ['WRONG_INVERSE', 'matches applying the same operation instead of its inverse', 'Q03', self::r($p['b'] + $p['a'])],
            ['SIGN_FLIP', 'matches the correct size with the opposite sign', 'N03', self::r(-$s)],
        ]);
    }

    public function signatureFeedback(array $p, string $code): ?string
    {
        return $code === 'WRONG_INVERSE' ? 'Your answer is what you get by doing the same operation to ' . Fmt::n($p['b']) . ' instead of undoing it.' : null;
    }
}
