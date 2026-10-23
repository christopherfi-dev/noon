<?php
declare(strict_types=1);

namespace AlgebraEngine\Items\Templates;

use AlgebraEngine\Checking\CheckOutcome;
use AlgebraEngine\Items\AbstractTemplate;
use AlgebraEngine\Items\PolynomialSignatures;
use AlgebraEngine\Support\RandomSource;

/** E06 constructed: expand a(x + b) with a positive factor. */
final class PositiveDistribution extends AbstractTemplate
{
    public function id(): string { return 'POSITIVE_DISTRIBUTION'; }
    public function skillId(): string { return 'E06'; }
    public function componentId(): string { return 'expansion'; }
    public function responseType(): string { return 'expression'; }

    public function parameterSpec(): array
    {
        return ['a' => 'integer 2..9 (outside factor)', 'b' => 'integer 1..12 (constant inside the parentheses); a*b != b + 1'];
    }

    public function generate(RandomSource $rng, ?array $avoid = null): array
    {
        return $this->sample(fn () => ['a' => $rng->int(2, 9), 'b' => $rng->int(1, 12)], $avoid);
    }

    public function validate(array $p): array
    {
        $e = [];
        self::onlyKeys($p, ['a', 'b'], $e);
        self::needInt($p, 'a', 2, 9, $e);
        self::needInt($p, 'b', 1, 12, $e);
        return $e;
    }

    public function display(array $p): string
    {
        return $p['a'] . '(x + ' . $p['b'] . ')';
    }

    public function render(array $p): string
    {
        return 'Expand ' . $this->display($p) . '. Write your answer without parentheses.';
    }

    public function answerSpec(array $p): array
    {
        return [
            'kind' => 'polynomial',
            'expected' => $p['a'] . '*x+' . ($p['a'] * $p['b']),
            'requested_form' => 'expanded',
            'units' => null,
            'tolerance' => null,
            'rubric' => [],
        ];
    }

    public function mathematicalObjects(array $p): array
    {
        return ['expression' => $p['a'] . '*(x+' . $p['b'] . ')', 'display' => $this->display($p), 'variables' => ['x']];
    }

    public function matchSignatures(array $p, string $response, CheckOutcome $outcome): array
    {
        [$a, $b] = [$p['a'], $p['b']];
        return PolynomialSignatures::match($outcome, ['x'], [
            ['FIRST_TERM_ONLY', 'matches distribution-to-first-term-only (the factor multiplied only the first term inside the parentheses)', 'A07', "$a*x+$b"],
            ['SECOND_TERM_ONLY', 'matches distribution-to-second-term-only (the factor multiplied only the constant)', 'A07', 'x+' . ($a * $b)],
        ], ['PRODUCT_FACT', 'matches the correct structure with a multiplication-fact error in a coefficient', 'N18', $this->answerSpec($p)['expected']]);
    }

    public function signatureFeedback(array $p, string $code): ?string
    {
        return match ($code) {
            'FIRST_TERM_ONLY' => DistributionTransfer::firstTermOnly($p['a']),
            'SECOND_TERM_ONLY' => 'Your answer multiplied only the ' . $p['b'] . ' by ' . $p['a'] . '.',
            default => null,
        };
    }
}
