<?php
declare(strict_types=1);

namespace AlgebraEngine\Items\Templates;

use AlgebraEngine\Checking\CheckOutcome;
use AlgebraEngine\Items\AbstractTemplate;
use AlgebraEngine\Items\Fmt;
use AlgebraEngine\Math\Rational;
use AlgebraEngine\Support\RandomSource;

/**
 * Q07 transfer: a new form, either a(x + b) + d = c or c = a(x + b).
 */
final class GroupedLinearTransfer extends AbstractTemplate
{
    public function id(): string { return 'GROUPED_LINEAR_TRANSFER'; }
    public function skillId(): string { return 'Q07'; }
    public function variantId(): string { return 'transfer'; }
    public function componentId(): string { return 'numeric_solution_transfer'; }
    public function responseType(): string { return 'scalar'; }

    public function parameterSpec(): array
    {
        return [
            'form' => 'string "PLUS_D" for a(x + b) + d = c, or "SWAPPED" for c = a(x + b)',
            'a' => 'integer 2..9',
            'b' => 'integer -9..9, not 0',
            'c' => 'integer, c - d = a*(s + b) for an integer solution s in -9..12',
            'd' => 'integer -15..15, not 0 for PLUS_D; exactly 0 for SWAPPED',
        ];
    }

    public function generate(RandomSource $rng, ?array $avoid = null): array
    {
        return $this->sample(function () use ($rng) {
            $form = $rng->pick(['PLUS_D', 'SWAPPED']);
            $a = $rng->int(2, 9);
            $b = $rng->int(-9, 9);
            $d = $form === 'PLUS_D' ? $rng->int(-15, 15) : 0;
            return ['form' => $form, 'a' => $a, 'b' => $b, 'c' => $a * ($rng->int(-9, 12) + $b) + $d, 'd' => $d];
        }, $avoid !== null ? array_diff_key($avoid, ['d' => true]) : null);
    }

    public function validate(array $p): array
    {
        $e = [];
        self::onlyKeys($p, ['form', 'a', 'b', 'c', 'd'], $e);
        $form = $p['form'] ?? null;
        if (!in_array($form, ['PLUS_D', 'SWAPPED'], true)) {
            return array_merge($e, ['parameters.form must be PLUS_D or SWAPPED']);
        }
        $a = self::needInt($p, 'a', 2, 9, $e);
        $b = self::needInt($p, 'b', -9, 9, $e, [0]);
        $c = self::needInt($p, 'c', -220, 220, $e);
        $d = $form === 'PLUS_D' ? self::needInt($p, 'd', -15, 15, $e, [0]) : self::needInt($p, 'd', 0, 0, $e);
        if ($a !== null && $b !== null && $c !== null && $d !== null) {
            if ($c - $d === 0) {
                $e[] = 'c - d must not be 0';
            } else {
                $e = array_merge($e, GroupedLinear::checkGrouped($a, $b, $c - $d));
            }
        }
        return $e;
    }

    public function display(array $p): string
    {
        $group = $p['a'] . '(' . Fmt::plus('x', $p['b']) . ')';
        return $p['form'] === 'PLUS_D'
            ? Fmt::plus($group, $p['d']) . ' = ' . Fmt::n($p['c'])
            : Fmt::n($p['c']) . ' = ' . $group;
    }

    public function render(array $p): string
    {
        return 'Solve ' . $this->display($p) . ' for x.';
    }

    public function answerSpec(array $p): array
    {
        return $this->scalarSpec(Rational::fromInt(intdiv($p['c'] - $p['d'], $p['a']) - $p['b']), 'value_of_x');
    }

    public function mathematicalObjects(array $p): array
    {
        $sig = [];
        foreach (GroupedLinear::signatureValues($p['a'], $p['b'], $p['c'] - $p['d']) as [$code, $v]) {
            $sig[$code] = $v->toString();
        }
        $eq = $p['form'] === 'PLUS_D'
            ? $p['a'] . '*(x+(' . $p['b'] . '))+(' . $p['d'] . ')=' . $p['c']
            : $p['c'] . '=' . $p['a'] . '*(x+(' . $p['b'] . '))';
        return ['equation' => $eq, 'display' => $this->display($p), 'variable' => 'x', 'signature_answers' => $sig];
    }

    public function matchSignatures(array $p, string $response, CheckOutcome $outcome): array
    {
        // With d moved first, the remaining structure is a(x + b) = c − d.
        return GroupedLinear::groupedSignatures($outcome, GroupedLinear::signatureValues($p['a'], $p['b'], $p['c'] - $p['d']), fn ($o, $s) => $this->matchScalar($o, $s));
    }

    public function signatureFeedback(array $p, string $code): ?string
    {
        return GroupedLinear::groupedFeedback($p['a'], $p['b'], $code);
    }
}
