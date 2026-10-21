<?php
declare(strict_types=1);

namespace AlgebraEngine\Items\Templates;

use AlgebraEngine\Checking\CheckOutcome;
use AlgebraEngine\Items\AbstractTemplate;
use AlgebraEngine\Items\Fmt;
use AlgebraEngine\Math\Rational;
use AlgebraEngine\Support\RandomSource;

/** N03: a + b with signed integers. */
final class SignedAdd extends AbstractTemplate
{
    public function id(): string { return 'SIGNED_ADD'; }
    public function skillId(): string { return 'N03'; }
    public function componentId(): string { return 'signed_sum'; }
    public function responseType(): string { return 'scalar'; }

    public function parameterSpec(): array
    {
        return ['a' => 'integer -20..20, not 0', 'b' => 'integer -20..20, not 0; at least one of a, b negative; |a| != |b|'];
    }

    public function generate(RandomSource $rng, ?array $avoid = null): array
    {
        return $this->sample(fn () => ['a' => $rng->int(-20, 20), 'b' => $rng->int(-20, 20)], $avoid);
    }

    public function validate(array $p): array
    {
        $e = [];
        self::onlyKeys($p, ['a', 'b'], $e);
        $a = self::needInt($p, 'a', -20, 20, $e, [0]);
        $b = self::needInt($p, 'b', -20, 20, $e, [0]);
        if ($a !== null && $b !== null) {
            if ($a > 0 && $b > 0) {
                $e[] = 'at least one of a, b must be negative';
            }
            if (abs($a) === abs($b)) {
                $e[] = '|a| and |b| must differ';
            }
        }
        return $e;
    }

    public function render(array $p): string
    {
        return 'Calculate ' . Fmt::n($p['a']) . ' + ' . Fmt::p($p['b']) . '.';
    }

    public function answerSpec(array $p): array
    {
        return $this->scalarSpec(self::r($p['a'] + $p['b']));
    }

    public function mathematicalObjects(array $p): array
    {
        return ['expression' => $p['a'] . '+(' . $p['b'] . ')', 'display' => Fmt::n($p['a']) . ' + ' . Fmt::p($p['b'])];
    }

    public function matchSignatures(array $p, string $response, CheckOutcome $outcome): array
    {
        $s = $p['a'] + $p['b'];
        $magnitudeSum = (abs($p['a']) + abs($p['b'])) * (abs($p['a']) > abs($p['b']) ? ($p['a'] <=> 0) : ($p['b'] <=> 0));
        $sigs = [['SIGN_FLIP', 'matches the correct size with the opposite sign', 'N02', self::r(-$s)]];
        if (($p['a'] < 0) !== ($p['b'] < 0)) {
            $sigs[] = ['ADDED_MAGNITUDES', 'matches adding the sizes of numbers with opposite signs', 'N02', self::r($magnitudeSum)];
        }
        return $this->matchScalar($outcome, $sigs);
    }
}
