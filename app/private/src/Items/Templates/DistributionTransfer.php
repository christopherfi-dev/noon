<?php
declare(strict_types=1);

namespace AlgebraEngine\Items\Templates;

use AlgebraEngine\Checking\CheckOutcome;
use AlgebraEngine\Items\AbstractTemplate;
use AlgebraEngine\Items\PolynomialSignatures;
use AlgebraEngine\Support\RandomSource;

/**
 * E06 transfer: a fresh structure — a coefficient inside the group a(px + q)
 * or a three-term group a(x + y + q).
 */
final class DistributionTransfer extends AbstractTemplate
{
    public function id(): string { return 'DISTRIBUTION_TRANSFER'; }
    public function skillId(): string { return 'E06'; }
    public function variantId(): string { return 'transfer'; }
    public function componentId(): string { return 'expansion_transfer'; }
    public function responseType(): string { return 'expression'; }

    public function inputHelp(): string
    {
        return 'Enter an expression using the letters in the question, for example 6x + 2y + 8.';
    }

    public function parameterSpec(): array
    {
        return [
            'form' => 'string "COEFF_INSIDE" for a(px + q) or "THREE_TERMS" for a(x + y + q)',
            'a' => 'integer 2..9',
            'p' => 'integer 2..6 for COEFF_INSIDE; exactly 1 for THREE_TERMS',
            'q' => 'integer 1..12',
        ];
    }

    public function generate(RandomSource $rng, ?array $avoid = null): array
    {
        return $this->sample(function () use ($rng) {
            $form = $rng->pick(['COEFF_INSIDE', 'THREE_TERMS']);
            return ['form' => $form, 'a' => $rng->int(2, 9), 'p' => $form === 'COEFF_INSIDE' ? $rng->int(2, 6) : 1, 'q' => $rng->int(1, 12)];
        }, $avoid !== null ? array_diff_key($avoid, ['p' => true]) : null);
    }

    public function validate(array $p): array
    {
        $e = [];
        self::onlyKeys($p, ['form', 'a', 'p', 'q'], $e);
        $form = $p['form'] ?? null;
        if (!in_array($form, ['COEFF_INSIDE', 'THREE_TERMS'], true)) {
            $e[] = 'parameters.form must be COEFF_INSIDE or THREE_TERMS';
            return $e;
        }
        self::needInt($p, 'a', 2, 9, $e);
        $form === 'COEFF_INSIDE' ? self::needInt($p, 'p', 2, 6, $e) : self::needInt($p, 'p', 1, 1, $e);
        self::needInt($p, 'q', 1, 12, $e);
        return $e;
    }

    public function display(array $p): string
    {
        return $p['form'] === 'COEFF_INSIDE'
            ? $p['a'] . '(' . $p['p'] . 'x + ' . $p['q'] . ')'
            : $p['a'] . '(x + y + ' . $p['q'] . ')';
    }

    public function render(array $p): string
    {
        return 'Expand ' . $this->display($p) . '. Write your answer without parentheses.';
    }

    private function vars(array $p): array
    {
        return $p['form'] === 'COEFF_INSIDE' ? ['x'] : ['x', 'y'];
    }

    public function answerSpec(array $p): array
    {
        [$a, $q] = [$p['a'], $p['q']];
        $expected = $p['form'] === 'COEFF_INSIDE'
            ? ($a * $p['p']) . '*x+' . ($a * $q)
            : "$a*x+$a*y+" . ($a * $q);
        return ['kind' => 'polynomial', 'expected' => $expected, 'requested_form' => 'expanded', 'units' => null, 'tolerance' => null, 'rubric' => []];
    }

    public function mathematicalObjects(array $p): array
    {
        $expr = $p['form'] === 'COEFF_INSIDE' ? $p['a'] . '*(' . $p['p'] . '*x+' . $p['q'] . ')' : $p['a'] . '*(x+y+' . $p['q'] . ')';
        return ['expression' => $expr, 'display' => $this->display($p), 'variables' => $this->vars($p)];
    }

    public function matchSignatures(array $p, string $response, CheckOutcome $outcome): array
    {
        [$a, $q] = [$p['a'], $p['q']];
        $patterns = $p['form'] === 'COEFF_INSIDE'
            ? [
                ['FIRST_TERM_ONLY', 'matches distribution-to-first-term-only (the factor multiplied only the first term inside the parentheses)', 'A07', ($a * $p['p']) . "*x+$q"],
                ['SECOND_TERM_ONLY', 'matches distribution-to-second-term-only', 'A07', $p['p'] . '*x+' . ($a * $q)],
            ]
            : [
                ['FIRST_TERM_ONLY', 'matches distribution-to-first-term-only (the factor multiplied only the first term inside the parentheses)', 'A07', "$a*x+y+$q"],
                ['NOT_EVERY_TERM', 'matches the factor reaching some but not all terms', 'A07', "$a*x+$a*y+$q"],
            ];
        return PolynomialSignatures::match($outcome, $this->vars($p), $patterns,
            ['PRODUCT_FACT', 'matches the correct structure with a multiplication-fact error in a coefficient', 'N18', $this->answerSpec($p)['expected']]);
    }

    public function signatureFeedback(array $p, string $code): ?string
    {
        return match ($code) {
            'FIRST_TERM_ONLY' => self::firstTermOnly($p['a']),
            'SECOND_TERM_ONLY', 'NOT_EVERY_TERM' => 'Your answer did not multiply every term in the parentheses by ' . $p['a'] . '.',
            default => null,
        };
    }

    /** Round 7 §8.3: the one learner-facing description of FIRST_TERM_ONLY, on Q07 and E06. */
    public static function firstTermOnly(int $a): string
    {
        return 'Your answer multiplied only the first term inside the parentheses by ' . $a . '.';
    }
}
