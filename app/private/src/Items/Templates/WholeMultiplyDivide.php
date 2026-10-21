<?php
declare(strict_types=1);

namespace AlgebraEngine\Items\Templates;

use AlgebraEngine\Items\AbstractTemplate;
use AlgebraEngine\Support\RandomSource;

/** N18: whole-number products a × b or exact quotients (a·b) ÷ b, factors 2..12. */
final class WholeMultiplyDivide extends AbstractTemplate
{
    public function id(): string { return 'WHOLE_MULTIPLY_DIVIDE'; }
    public function skillId(): string { return 'N18'; }
    public function componentId(): string { return 'whole_product_quotient'; }
    public function responseType(): string { return 'scalar'; }

    public function parameterSpec(): array
    {
        return ['op' => 'string "multiply" or "divide"', 'a' => 'integer 2..12', 'b' => 'integer 2..12 (for divide the item is (a*b) / b)'];
    }

    public function generate(RandomSource $rng, ?array $avoid = null): array
    {
        return $this->sample(fn () => ['op' => $rng->pick(['multiply', 'divide']), 'a' => $rng->int(2, 12), 'b' => $rng->int(2, 12)], $avoid);
    }

    public function validate(array $p): array
    {
        $e = [];
        self::onlyKeys($p, ['op', 'a', 'b'], $e);
        if (!in_array($p['op'] ?? null, ['multiply', 'divide'], true)) {
            $e[] = 'parameters.op must be "multiply" or "divide"';
        }
        self::needInt($p, 'a', 2, 12, $e);
        self::needInt($p, 'b', 2, 12, $e);
        return $e;
    }

    public function render(array $p): string
    {
        return $p['op'] === 'multiply'
            ? 'Calculate ' . $p['a'] . ' × ' . $p['b'] . '.'
            : 'Calculate ' . ($p['a'] * $p['b']) . ' ÷ ' . $p['b'] . '.';
    }

    public function answerSpec(array $p): array
    {
        return $this->scalarSpec(self::r($p['op'] === 'multiply' ? $p['a'] * $p['b'] : $p['a']));
    }

    public function mathematicalObjects(array $p): array
    {
        return $p['op'] === 'multiply'
            ? ['expression' => $p['a'] . '*' . $p['b']]
            : ['expression' => ($p['a'] * $p['b']) . '/' . $p['b']];
    }
}
