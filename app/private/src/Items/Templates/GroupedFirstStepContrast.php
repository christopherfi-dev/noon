<?php
declare(strict_types=1);

namespace AlgebraEngine\Items\Templates;

use AlgebraEngine\Checking\CheckOutcome;
use AlgebraEngine\Items\AbstractTemplate;
use AlgebraEngine\Items\ClassificationSupport as CS;
use AlgebraEngine\Items\Fmt;
use AlgebraEngine\Math\EquationParser;
use AlgebraEngine\Math\LinearEquation;
use AlgebraEngine\Support\RandomSource;

/**
 * Q07 contrast: given a(x + b) = c, select every valid first step. Each
 * option shows the step and the equation it produces; the host derives the
 * key by solving each displayed equation and comparing solution sets.
 */
final class GroupedFirstStepContrast extends AbstractTemplate
{
    public const POOL = ['DIVIDE_BOTH', 'DISTRIBUTE', 'SUBTRACT_INSIDE', 'MULTIPLY_X_ONLY', 'DIVIDE_LEFT_ONLY'];

    public function id(): string { return 'GROUPED_FIRST_STEP_CONTRAST'; }
    public function skillId(): string { return 'Q07'; }
    public function variantId(): string { return 'contrast'; }
    public function componentId(): string { return 'first_step_contrast'; }
    public function responseType(): string { return 'classification'; }

    public function parameterSpec(): array
    {
        return [
            'a' => 'integer 2..9',
            'b' => 'integer -9..9, not 0',
            'c' => 'integer, c = a*(s + b) for an integer solution s in -9..12, c != 0',
            'options' => 'list of 4 distinct codes, in display order, from ' . implode(', ', self::POOL) . '; at least one valid and one invalid',
        ];
    }

    public function generate(RandomSource $rng, ?array $avoid = null): array
    {
        return $this->sample(function () use ($rng) {
            $a = $rng->int(2, 9);
            $b = $rng->int(-9, 9);
            return ['a' => $a, 'b' => $b, 'c' => $a * ($rng->int(-9, 12) + $b), 'options' => array_slice($rng->shuffle(self::POOL), 0, 4)];
        }, $avoid);
    }

    public function validate(array $p): array
    {
        $e = [];
        self::onlyKeys($p, ['a', 'b', 'c', 'options'], $e);
        $a = self::needInt($p, 'a', 2, 9, $e);
        $b = self::needInt($p, 'b', -9, 9, $e, [0]);
        $c = self::needInt($p, 'c', -200, 200, $e, [0]);
        if (!CS::validateCodes($p['options'] ?? null, self::POOL, $e) || $a === null || $b === null || $c === null) {
            return $e;
        }
        $grouped = GroupedLinear::checkGrouped($a, $b, $c);
        if ($grouped !== []) {
            return array_merge($e, $grouped);
        }
        $key = $this->keyLetters($p);
        if ($key === [] || count($key) === 4) {
            $e[] = 'options must include at least one valid and one invalid first step';
        }
        return $e;
    }

    private function original(array $p): string
    {
        return $p['a'] . '(' . Fmt::plus('x', $p['b']) . ') = ' . Fmt::n($p['c']);
    }

    private function optionText(string $code, int $a, int $b, int $c): string
    {
        $inner = Fmt::plus('x', $b);
        return match ($code) {
            'DIVIDE_BOTH' => 'Divide both sides by ' . $a . ': ' . $inner . ' = ' . Fmt::n(intdiv($c, $a)),
            'DISTRIBUTE' => 'Multiply both terms in the parentheses by ' . $a . ': ' . Fmt::plus($a . 'x', $a * $b) . ' = ' . Fmt::n($c),
            'SUBTRACT_INSIDE' => ($b > 0 ? 'Subtract ' . $b : 'Add ' . (-$b)) . ' to undo the ' . Fmt::n($b) . ': ' . $a . 'x = ' . Fmt::n($c - $b),
            'MULTIPLY_X_ONLY' => 'Multiply only the x by ' . $a . ': ' . Fmt::plus($a . 'x', $b) . ' = ' . Fmt::n($c),
            'DIVIDE_LEFT_ONLY' => 'Divide only the left side by ' . $a . ': ' . $inner . ' = ' . Fmt::n($c),
        };
    }

    /** @return list<array{letter:string,code:string,text:string}> */
    private function lettered(array $p): array
    {
        return CS::letter(array_map(fn ($code) => ['code' => $code, 'text' => $this->optionText($code, $p['a'], $p['b'], $p['c'])], $p['options']));
    }

    /** Key letters derived by solving each displayed resulting equation. @return list<string> */
    private function keyLetters(array $p): array
    {
        [$l, $r] = EquationParser::parse($this->original($p));
        $original = LinearEquation::solve($l, $r);
        $key = [];
        foreach ($this->lettered($p) as $o) {
            $equation = substr($o['text'], strrpos($o['text'], ': ') + 2);
            [$ol, $or] = EquationParser::parse($equation);
            $sol = LinearEquation::solve($ol, $or);
            if ($sol->kind === 'UNIQUE' && $original->kind === 'UNIQUE' && $sol->value->equals($original->value)) {
                $key[] = $o['letter'];
            }
        }
        return $key;
    }

    public function render(array $p): string
    {
        return CS::promptLines(
            'Here is an equation: ' . $this->original($p) . '. Which of these first steps give an equation with exactly the same solution? Select every one that does.',
            $this->lettered($p),
        );
    }

    public function answerSpec(array $p): array
    {
        return CS::spec($this->keyLetters($p));
    }

    public function mathematicalObjects(array $p): array
    {
        return ['equation' => $p['a'] . '*(x+(' . $p['b'] . '))=' . $p['c'], 'display' => $this->original($p), 'options' => $this->lettered($p), 'option_letters' => CS::LETTERS];
    }

    public function matchSignatures(array $p, string $response, CheckOutcome $outcome): array
    {
        if ($outcome->verdict !== 'INCORRECT') {
            return [];
        }
        $chosen = CS::selectedCodes($outcome, $this->lettered($p));
        if (in_array('MULTIPLY_X_ONLY', $chosen, true)) {
            return [['code' => 'DIST_X_ONLY_CHOICE', 'label' => 'matches distribution-to-x-only (accepted multiplying only the x)', 'branch' => 'E06']];
        }
        if (array_intersect($chosen, ['SUBTRACT_INSIDE', 'DIVIDE_LEFT_ONLY']) !== []) {
            return [['code' => 'INVERSE_OPERATION_CHOICE', 'label' => 'matches accepting a step that does not keep both sides equal', 'branch' => 'Q06']];
        }
        return [];
    }

    public function signatureFeedback(array $p, string $code): ?string
    {
        return $code === 'DIST_X_ONLY_CHOICE' ? 'One step you chose multiplies only the x by ' . $p['a'] . '.' : null;
    }
}
