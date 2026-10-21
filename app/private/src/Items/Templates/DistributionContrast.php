<?php
declare(strict_types=1);

namespace AlgebraEngine\Items\Templates;

use AlgebraEngine\Checking\CheckOutcome;
use AlgebraEngine\Items\AbstractTemplate;
use AlgebraEngine\Items\ClassificationSupport as CS;
use AlgebraEngine\Math\ExpressionParser;
use AlgebraEngine\Math\Polynomial;
use AlgebraEngine\Support\RandomSource;

/**
 * E06 contrast: does the factor reach every term? The learner selects every
 * expression equal to a(x + b). The key is derived by polynomial identity of
 * each displayed option text, so the key always matches what is shown.
 */
final class DistributionContrast extends AbstractTemplate
{
    public const POOL = ['EXPANDED', 'UNSIMPLIFIED', 'FIRST_ONLY', 'SECOND_ONLY', 'ADDED_FACTOR'];

    public function id(): string { return 'DISTRIBUTION_CONTRAST'; }
    public function skillId(): string { return 'E06'; }
    public function variantId(): string { return 'contrast'; }
    public function componentId(): string { return 'reach_every_term_contrast'; }
    public function responseType(): string { return 'classification'; }

    public function parameterSpec(): array
    {
        return [
            'a' => 'integer 2..9',
            'b' => 'integer 1..12',
            'options' => 'list of 4 distinct codes, in display order, from ' . implode(', ', self::POOL) . '; at least one equal and one not equal',
        ];
    }

    public function generate(RandomSource $rng, ?array $avoid = null): array
    {
        return $this->sample(fn () => [
            'a' => $rng->int(2, 9),
            'b' => $rng->int(1, 12),
            'options' => array_slice($rng->shuffle(self::POOL), 0, 4),
        ], $avoid);
    }

    public function validate(array $p): array
    {
        $e = [];
        self::onlyKeys($p, ['a', 'b', 'options'], $e);
        $a = self::needInt($p, 'a', 2, 9, $e);
        $b = self::needInt($p, 'b', 1, 12, $e);
        if (!CS::validateCodes($p['options'] ?? null, self::POOL, $e) || $a === null || $b === null) {
            return $e;
        }
        $key = $this->keyLetters($p);
        if ($key === [] || count($key) === 4) {
            $e[] = 'options must include at least one equal and one not-equal expression';
        }
        return $e;
    }

    private function optionText(string $code, int $a, int $b): string
    {
        return match ($code) {
            'EXPANDED' => $a . 'x + ' . ($a * $b),
            'UNSIMPLIFIED' => $a . ' · x + ' . $a . ' · ' . $b,
            'FIRST_ONLY' => $a . 'x + ' . $b,
            'SECOND_ONLY' => 'x + ' . ($a * $b),
            'ADDED_FACTOR' => 'x + ' . ($a + $b),
        };
    }

    /** @return list<array{letter:string,code:string,text:string}> */
    private function lettered(array $p): array
    {
        return CS::letter(array_map(fn ($c) => ['code' => $c, 'text' => $this->optionText($c, $p['a'], $p['b'])], $p['options']));
    }

    /** @return list<string> */
    private function keyLetters(array $p): array
    {
        $target = Polynomial::fromNode(ExpressionParser::parse($p['a'] . '*(x+' . $p['b'] . ')'));
        $key = [];
        foreach ($this->lettered($p) as $o) {
            if (Polynomial::fromNode(ExpressionParser::parse($o['text']))->equals($target)) {
                $key[] = $o['letter'];
            }
        }
        return $key;
    }

    public function render(array $p): string
    {
        return CS::promptLines(
            'Which of these expressions are equal to ' . $p['a'] . '(x + ' . $p['b'] . ') for every value of x? Select every one that is.',
            $this->lettered($p),
        );
    }

    public function answerSpec(array $p): array
    {
        return CS::spec($this->keyLetters($p));
    }

    public function mathematicalObjects(array $p): array
    {
        return ['expression' => $p['a'] . '*(x+' . $p['b'] . ')', 'options' => $this->lettered($p), 'option_letters' => CS::LETTERS];
    }

    public function matchSignatures(array $p, string $response, CheckOutcome $outcome): array
    {
        if ($outcome->verdict !== 'INCORRECT') {
            return [];
        }
        $chosen = CS::selectedCodes($outcome, $this->lettered($p));
        if (array_intersect($chosen, ['FIRST_ONLY', 'SECOND_ONLY', 'ADDED_FACTOR']) !== []) {
            return [['code' => 'FACTOR_NOT_EVERY_TERM_CHOICE', 'label' => 'matches accepting an expression where the factor does not reach every term', 'branch' => 'A07']];
        }
        return [];
    }
}
