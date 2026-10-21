<?php
declare(strict_types=1);

namespace AlgebraEngine\Items\Templates;

use AlgebraEngine\Checking\CheckOutcome;
use AlgebraEngine\Items\AbstractTemplate;
use AlgebraEngine\Items\Fmt;
use AlgebraEngine\Math\Rational;
use AlgebraEngine\Support\RandomSource;

/** N05: a × b with signed integers. */
final class SignedMultiply extends AbstractTemplate
{
    public function id(): string { return 'SIGNED_MULTIPLY'; }
    public function skillId(): string { return 'N05'; }
    public function componentId(): string { return 'signed_product'; }
    public function responseType(): string { return 'scalar'; }

    public function parameterSpec(): array
    {
        return ['a' => 'integer -12..12, not -1, 0, 1', 'b' => 'integer -12..12, not -1, 0, 1; at least one of a, b negative'];
    }

    public function generate(RandomSource $rng, ?array $avoid = null): array
    {
        return $this->sample(fn () => ['a' => $rng->int(-12, 12), 'b' => $rng->int(-12, 12)], $avoid);
    }

    public function validate(array $p): array
    {
        $e = [];
        self::onlyKeys($p, ['a', 'b'], $e);
        $a = self::needInt($p, 'a', -12, 12, $e, [-1, 0, 1]);
        $b = self::needInt($p, 'b', -12, 12, $e, [-1, 0, 1]);
        if ($a !== null && $b !== null && $a > 0 && $b > 0) {
            $e[] = 'at least one of a, b must be negative';
        }
        return $e;
    }

    public function render(array $p): string
    {
        return 'Calculate ' . Fmt::p($p['a']) . ' × ' . Fmt::p($p['b']) . '.';
    }

    public function answerSpec(array $p): array
    {
        return $this->scalarSpec(self::r($p['a'] * $p['b']));
    }

    public function mathematicalObjects(array $p): array
    {
        return ['expression' => '(' . $p['a'] . ')*(' . $p['b'] . ')', 'display' => Fmt::p($p['a']) . ' × ' . Fmt::p($p['b'])];
    }

    public function matchSignatures(array $p, string $response, CheckOutcome $outcome): array
    {
        $prod = $p['a'] * $p['b'];
        return $this->matchScalar($outcome, [
            ['SIGN_FLIP', 'matches the correct size with the opposite sign', 'N02', self::r(-$prod)],
            ['MAGNITUDE', 'matches the correct sign with a different size (multiplication fact)', 'N18',
                fn (Rational $r) => $r->sign() === ($prod <=> 0) && !$r->abs()->equals(self::r(abs($prod)))],
        ]);
    }
}
