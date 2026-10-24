<?php
declare(strict_types=1);

namespace AlgebraEngine\Items\Templates;

use AlgebraEngine\Checking\CheckOutcome;
use AlgebraEngine\Items\AbstractTemplate;
use AlgebraEngine\Items\Fmt;
use AlgebraEngine\Math\Rational;
use AlgebraEngine\Support\RandomSource;

/** N06: a ÷ b with an exact signed integer quotient q (a = q·b). */
final class SignedDivide extends AbstractTemplate
{
    public function id(): string { return 'SIGNED_DIVIDE'; }
    public function skillId(): string { return 'N06'; }
    public function componentId(): string { return 'signed_quotient'; }
    public function responseType(): string { return 'scalar'; }

    public function parameterSpec(): array
    {
        return ['a' => 'integer dividend, a = q*b with q integer -12..12 not 0', 'b' => 'integer divisor -12..12, not -1, 0, 1; at least one of a, b negative'];
    }

    public function generate(RandomSource $rng, ?array $avoid = null): array
    {
        return $this->sample(function () use ($rng) {
            $b = $rng->int(-12, 12);
            $q = $rng->int(-12, 12);
            return ['a' => $q * $b, 'b' => $b];
        }, $avoid);
    }

    public function validate(array $p): array
    {
        $e = [];
        self::onlyKeys($p, ['a', 'b'], $e);
        $a = self::needInt($p, 'a', -144, 144, $e, [0]);
        $b = self::needInt($p, 'b', -12, 12, $e, [-1, 0, 1]);
        if ($a !== null && $b !== null) {
            if ($a % $b !== 0) {
                $e[] = 'a must be an exact multiple of b';
            } elseif (abs(intdiv($a, $b)) > 12) {
                $e[] = 'the quotient must be between -12 and 12';
            }
            if ($a > 0 && $b > 0) {
                $e[] = 'at least one of a, b must be negative';
            }
        }
        return $e;
    }

    public function render(array $p): string
    {
        return 'Calculate ' . Fmt::n($p['a']) . ' ÷ ' . Fmt::p($p['b']) . '.';
    }

    public function answerSpec(array $p): array
    {
        return $this->scalarSpec(self::r(intdiv($p['a'], $p['b'])));
    }

    public function mathematicalObjects(array $p): array
    {
        return ['expression' => '(' . $p['a'] . ')/(' . $p['b'] . ')', 'display' => Fmt::n($p['a']) . ' ÷ ' . Fmt::p($p['b'])];
    }

    public function matchSignatures(array $p, string $response, CheckOutcome $outcome): array
    {
        $q = intdiv($p['a'], $p['b']);
        return $this->matchScalar($outcome, [
            ['SIGN_FLIP', 'matches the correct size with the opposite sign', 'N05', self::r(-$q)],
            ['MAGNITUDE', 'matches the correct sign with a different size', 'N18',
                fn (Rational $r) => $r->sign() === ($q <=> 0) && !$r->abs()->equals(self::r(abs($q)))],
        ]);
    }
}
