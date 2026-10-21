<?php
declare(strict_types=1);

namespace AlgebraEngine\Items;

use AlgebraEngine\Items\ClassificationSupport as CS;
use AlgebraEngine\Math\ExpressionParser;
use AlgebraEngine\Math\Polynomial;
use AlgebraEngine\Math\Rational;
use AlgebraEngine\Support\Ids;
use AlgebraEngine\Support\RandomSource;

/**
 * Builds guided-repair plans (addendum D1). A plan is a fresh problem broken
 * into steps; every step is a question asking for one learner action. Each
 * step may hold smaller questions that are used only when the learner is
 * stuck. No step states a worked example, a full solution or an answer.
 */
final class SocraticPlanner
{
    public function __construct(private readonly ItemFactory $factory)
    {
    }

    /**
     * @param array{route?:string,avoid?:?array} $opts
     * @return array<string,mixed>
     */
    public function plan(string $skillId, RandomSource $rng, bool $faded = false, array $opts = []): array
    {
        $template = $this->factory->templateFor($skillId, 'constructed');
        $route = $opts['route'] ?? 'divide';
        $plan = null;
        for ($i = 0; $i < 60; $i++) {
            $params = $template->generate($rng, $opts['avoid'] ?? null);
            $plan = $this->build($skillId, $params, $rng, $route);
            if ($this->answersAbsentFromProblem($plan)) {
                break;
            }
        }
        if ($faded) {
            $plan = $this->fade($plan);
        }
        $plan['plan_id'] = Ids::make('plan');
        $plan['faded'] = $faded;
        $plan['template_id'] = $template->id();
        return $plan;
    }

    /** Guided problems avoid step answers that already appear in the problem text. */
    private function answersAbsentFromProblem(array $plan): bool
    {
        $numbers = [];
        $text = str_replace(Fmt::MINUS, '-', $plan['problem']);
        preg_match_all('/(?<![\d.])-?\d+(?:\/\d+)?/', $text, $m);
        foreach ($m[0] as $n) {
            $numbers[$n] = true;
        }
        foreach ($this->flatten($plan['steps']) as $step) {
            if ($step['answer']['kind'] === 'exact_rational' && isset($numbers[$step['answer']['expected']])) {
                return false;
            }
        }
        return true;
    }

    /** @return list<array<string,mixed>> */
    public function flatten(array $steps): array
    {
        $out = [];
        foreach ($steps as $s) {
            $out[] = $s;
            foreach ($s['smaller'] as $sub) {
                $out[] = $sub;
            }
        }
        return $out;
    }

    /** Faded support: the whole problem is asked first; the full step sequence becomes its smaller questions. */
    private function fade(array $plan): array
    {
        $final = null;
        $others = [];
        foreach ($plan['steps'] as $s) {
            if ($s['final']) {
                $final = $s;
            } else {
                $s['smaller'] = [];
                $others[] = $s;
            }
        }
        $final['prompt'] = $plan['whole_prompt'];
        $final['alt_prompt'] = null;
        $final['smaller'] = array_map(function ($s, $i) use ($final) {
            $s['id'] = $final['id'] . '.' . ($i + 1);
            return $s;
        }, $others, array_keys($others));
        $plan['steps'] = [$final];
        return $plan;
    }

    // ---------------------------------------------------------------- builders

    private function build(string $skillId, array $p, RandomSource $rng, string $route): array
    {
        [$problem, $whole, $steps] = match ($skillId) {
            'N03' => $this->n03($p, $rng),
            'N04' => $this->n04($p),
            'N05' => $this->n05($p, $rng),
            'N06' => $this->n06($p, $rng),
            'N18' => $this->n18($p),
            'Q04' => $this->q04($p, $rng),
            'Q05' => $this->q05($p, $rng),
            'Q06' => $this->q06($p, $rng),
            'Q07' => $route === 'expand' ? $this->q07Expand($p) : $this->q07Divide($p, $rng),
            'E06' => $this->e06($p, $rng),
            default => throw new \InvalidArgumentException('No guided plan for ' . $skillId),
        };
        return ['skill_id' => $skillId, 'params' => $p, 'problem' => $problem, 'whole_prompt' => $whole, 'route' => $route, 'steps' => $steps];
    }

    private function n03(array $p, RandomSource $rng): array
    {
        [$a, $b] = [$p['a'], $p['b']];
        $disp = Fmt::n($a) . ' + ' . Fmt::p($b);
        $sum = $a + $b;
        $sign = $this->signStep('s?', 'Is the value of ' . $disp . ' positive or negative?', $sum, 'N02', $rng);
        if (($a < 0) !== ($b < 0)) {
            $far = abs($a) > abs($b) ? $a : $b;
            $steps = [
                $this->choice('s1', 'In ' . $disp . ', which number is farther from 0?', [
                    ['code' => 'A', 'text' => Fmt::n($a), 'valid' => $far === $a],
                    ['code' => 'B', 'text' => Fmt::n($b), 'valid' => $far === $b],
                ], 'N02', null, 'select_one'),
                $this->rename($sign, 's2'),
                $this->scalar('s3', 'What is the difference between ' . abs($a) . ' and ' . abs($b) . '?', abs(abs($a) - abs($b)), 'N17',
                    [$this->scalar('s3.1', 'Count up from ' . min(abs($a), abs($b)) . ' to ' . max(abs($a), abs($b)) . '. How many steps is that?', abs(abs($a) - abs($b)), 'N17')]),
                $this->scalar('s4', 'What is ' . $disp . '?', $sum, 'N02', [], 'Use the sign you chose and the difference you found. What is ' . $disp . '?', true),
            ];
        } else {
            $steps = [
                $this->rename($this->signStep('s1', 'Both numbers in ' . $disp . ' are negative. Is their sum positive or negative?', $sum, 'N02', $rng), 's1'),
                $this->scalar('s2', 'What is ' . abs($a) . ' + ' . abs($b) . '?', abs($a) + abs($b), 'N17'),
                $this->scalar('s3', 'What is ' . $disp . '?', $sum, 'N02', [], 'Use the sign and the total you found. What is ' . $disp . '?', true),
            ];
        }
        return [$disp, 'What is ' . $disp . '?', $steps];
    }

    private function n04(array $p): array
    {
        [$a, $b] = [$p['a'], $p['b']];
        $disp = Fmt::n($a) . ' ' . Fmt::MINUS . ' ' . Fmt::p($b);
        $steps = [
            $this->scalar('s1', 'Subtracting a number gives the same result as adding its opposite. What is the opposite of ' . Fmt::n($b) . '?', -$b, 'N02', [],
                'What number is the same distance from 0 as ' . Fmt::n($b) . ', on the other side of 0?'),
            $this->scalar('s2', 'What is ' . $disp . '?', $a - $b, 'N03',
                [$this->scalar('s2.1', 'What is ' . Fmt::n($a) . ' + ' . Fmt::p(-$b) . '?', $a - $b, 'N03')],
                'Rewrite it as an addition first. What is ' . $disp . '?', true),
        ];
        return [$disp, 'What is ' . $disp . '?', $steps];
    }

    private function n05(array $p, RandomSource $rng): array
    {
        [$a, $b] = [$p['a'], $p['b']];
        $disp = Fmt::p($a) . ' × ' . Fmt::p($b);
        $negatives = ($a < 0 ? 1 : 0) + ($b < 0 ? 1 : 0);
        $steps = [
            $this->scalar('s1', 'Leave the signs aside for a moment. What is ' . abs($a) . ' × ' . abs($b) . '?', abs($a * $b), 'N18', [],
                'What is ' . abs($b) . ' × ' . abs($a) . '?'),
            $this->signStep('s2', ($negatives === 2 ? 'Both numbers are negative. ' : 'Exactly one of the numbers is negative. ') . 'Is ' . $disp . ' positive or negative?', $a * $b, 'N02', $rng),
            $this->scalar('s3', 'What is ' . $disp . '?', $a * $b, 'N02', [], 'Use the size and the sign you found. What is ' . $disp . '?', true),
        ];
        return [$disp, 'What is ' . $disp . '?', $steps];
    }

    private function n06(array $p, RandomSource $rng): array
    {
        [$a, $b] = [$p['a'], $p['b']];
        $q = intdiv($a, $b);
        $disp = Fmt::n($a) . ' ÷ ' . Fmt::p($b);
        $steps = [
            $this->scalar('s1', 'Leave the signs aside for a moment. What is ' . abs($a) . ' ÷ ' . abs($b) . '?', abs($q), 'N18',
                [$this->scalar('s1.1', abs($b) . ' times what number is ' . abs($a) . '?', abs($q), 'N18')]),
            $this->signStep('s2', 'Is ' . $disp . ' positive or negative?', $q, 'N05', $rng),
            $this->scalar('s3', 'What is ' . $disp . '?', $q, 'N05', [], 'Use the size and the sign you found. What is ' . $disp . '?', true),
        ];
        return [$disp, 'What is ' . $disp . '?', $steps];
    }

    private function n18(array $p): array
    {
        [$a, $b] = [$p['a'], $p['b']];
        if ($p['op'] === 'divide') {
            $n = $a * $b;
            $disp = $n . ' ÷ ' . $b;
            $steps = [
                $this->scalar('s1', 'Division asks a missing-factor question. ' . $b . ' times what number makes ' . $n . '?', $a, 'N17',
                    [$this->scalar('s1.1', 'Count by ' . $b . 's: ' . $b . ', ' . (2 * $b) . ', ' . (3 * $b) . ', and so on. How many numbers do you say to reach ' . $n . '?', $a, 'N17')]),
                $this->scalar('s2', 'What is ' . $disp . '?', $a, 'N17', [], null, true),
            ];
            return [$disp, 'What is ' . $disp . '?', $steps];
        }
        $disp = $a . ' × ' . $b;
        [$s, $l] = [min($a, $b), max($a, $b)];
        if ($l < 3) {
            $steps = [
                $this->scalar('s1', 'What is ' . $s . ' + ' . $s . '?', 2 * $s, 'N17'),
                $this->scalar('s2', 'What is ' . $disp . '?', $a * $b, 'N17', [], null, true),
            ];
            return [$disp, 'What is ' . $disp . '?', $steps];
        }
        $k = $l > 10 ? 10 : ($l >= 6 ? 5 : 2);
        $steps = [
            $this->scalar('s1', 'What is ' . $s . ' × ' . $k . '?', $s * $k, 'N17'),
            $this->scalar('s2', 'What is ' . $s . ' × ' . ($l - $k) . '?', $s * ($l - $k), 'N17'),
            $this->scalar('s3', $l . ' is ' . $k . ' + ' . ($l - $k) . ', so ' . $s . ' × ' . $l . ' is ' . $s . ' × ' . $k . ' plus ' . $s . ' × ' . ($l - $k) . '. What is ' . $disp . '?', $a * $b, 'N17',
                [$this->scalar('s3.1', 'Add your two results. What is their total?', $a * $b, 'N17')], null, true),
        ];
        return [$disp, 'What is ' . $disp . '?', $steps];
    }

    private function q04(array $p, RandomSource $rng): array
    {
        [$a, $b] = [$p['a'], $p['b']];
        $lhs = Fmt::plus('x', $a);
        $eq = $lhs . ' = ' . Fmt::n($b);
        $k = abs($a);
        $ops = $rng->shuffle([
            ['code' => 'SUB', 'text' => 'Subtract ' . $k, 'op' => ['add', -$k]],
            ['code' => 'ADD', 'text' => 'Add ' . $k, 'op' => ['add', $k]],
            ['code' => 'MUL', 'text' => 'Multiply by ' . $k, 'op' => ['mul', $k]],
            ['code' => 'DIV', 'text' => 'Divide by ' . $k, 'op' => ['div', $k]],
        ]);
        $ops = $this->markOps($ops, "x+($a)", 'x');
        $arith = ($a > 0 ? Fmt::n($b) . ' ' . Fmt::MINUS . ' ' . $a : Fmt::n($b) . ' + ' . (-$a));
        $steps = [
            $this->choice('s1', 'Which operation, done to both sides of ' . $eq . ', leaves x by itself?', $ops, 'Q03', 'That operation does not leave x by itself.', 'select_one'),
            $this->scalar('s2', 'Undo the ' . Fmt::n($a) . ' on both sides, so x is alone on the left. What is x?', $b - $a, 'N03',
                [$this->scalar('s2.1', 'What is ' . $arith . '?', $b - $a, 'N03')], null, true),
        ];
        return [$eq, 'Solve ' . $eq . '. What is x?', $steps];
    }

    private function q05(array $p, RandomSource $rng): array
    {
        [$a, $b] = [$p['a'], $p['b']];
        $s = intdiv($b, $a);
        $eq = Fmt::cx($a) . ' = ' . Fmt::n($b);
        $ops = $rng->shuffle([
            ['code' => 'DIV', 'text' => 'Divide by ' . Fmt::n($a), 'op' => ['div', $a]],
            ['code' => 'DIVN', 'text' => 'Divide by ' . Fmt::n(-$a), 'op' => ['div', -$a]],
            ['code' => 'MUL', 'text' => 'Multiply by ' . Fmt::n($a), 'op' => ['mul', $a]],
            ['code' => 'SUB', 'text' => 'Subtract ' . Fmt::n($a), 'op' => ['add', -$a]],
        ]);
        $ops = $this->markOps($ops, "($a)*x", 'x');
        $steps = [
            $this->choice('s1', 'Which operation, done to both sides of ' . $eq . ', leaves x by itself?', $ops, 'Q03', 'That operation does not leave x by itself.', 'select_one'),
            $this->scalar('s2', 'Divide both sides by ' . Fmt::p($a) . '. What is x?', $s, 'N06', [
                $this->scalar('s2.1', 'Leave the signs aside for a moment. What is ' . abs($b) . ' ÷ ' . abs($a) . '?', abs($s), 'N06'),
                $this->signStep('s2.2', 'Is ' . Fmt::n($b) . ' ÷ ' . Fmt::p($a) . ' positive or negative?', $s, 'N06', $rng),
            ], null, true),
        ];
        return [$eq, 'Solve ' . $eq . '. What is x?', $steps];
    }

    private function q06(array $p, RandomSource $rng): array
    {
        [$a, $b, $c] = [$p['a'], $p['b'], $p['c']];
        $s = intdiv($c - $b, $a);
        $lhs = Fmt::plus(Fmt::cx($a), $b);
        $eq = $lhs . ' = ' . Fmt::n($c);
        $k = abs($b);
        $ops = $rng->shuffle([
            ['code' => 'SUB', 'text' => 'Subtract ' . $k, 'op' => ['add', -$k]],
            ['code' => 'ADD', 'text' => 'Add ' . $k, 'op' => ['add', $k]],
            ['code' => 'DIV', 'text' => 'Divide by ' . Fmt::n($a), 'op' => ['div', $a]],
            ['code' => 'MUL', 'text' => 'Multiply by ' . Fmt::n($a), 'op' => ['mul', $a]],
        ]);
        $ops = $this->markOps($ops, "($a)*x+($b)", "($a)*x");
        $arith = $b > 0 ? Fmt::n($c) . ' ' . Fmt::MINUS . ' ' . $b : Fmt::n($c) . ' + ' . (-$b);
        $rest = $c - $b;
        $steps = [
            $this->choice('s1', 'Which operation, done to both sides of ' . $eq . ', leaves ' . Fmt::cx($a) . ' by itself on the left?', $ops, 'Q04', 'That operation does not leave ' . Fmt::cx($a) . ' by itself.', 'select_one'),
            $this->scalar('s2', 'Undo the ' . Fmt::n($b) . ' on both sides. What does the right side, ' . Fmt::n($c) . ', become?', $rest, 'Q04',
                [$this->scalar('s2.1', 'What is ' . $arith . '?', $rest, 'Q04')]),
            $this->scalar('s3', 'Now the equation is ' . Fmt::cx($a) . ' = ' . Fmt::n($rest) . '. What is x?', $s, 'Q05', [
                $this->scalar('s3.1', 'Leave the signs aside for a moment. What is ' . abs($rest) . ' ÷ ' . abs($a) . '?', abs($s), 'Q05'),
                $this->signStep('s3.2', 'Is ' . Fmt::n($rest) . ' ÷ ' . Fmt::p($a) . ' positive or negative?', $s, 'Q05', $rng),
            ], null, true),
        ];
        return [$eq, 'Solve ' . $eq . '. What is x?', $steps];
    }

    private function q07Divide(array $p, RandomSource $rng): array
    {
        [$a, $b, $c] = [$p['a'], $p['b'], $p['c']];
        $inner = Fmt::plus('x', $b);
        $eq = $a . '(' . $inner . ') = ' . Fmt::n($c);
        $q = intdiv($c, $a);
        $s = $q - $b;
        $ops = $rng->shuffle([
            ['code' => 'DIV', 'text' => 'Divide by ' . $a, 'op' => ['div', $a]],
            ['code' => 'SUB', 'text' => 'Subtract ' . $a, 'op' => ['add', -$a]],
            ['code' => 'MUL', 'text' => 'Multiply by ' . $a, 'op' => ['mul', $a]],
            ['code' => 'SUBB', 'text' => ($b > 0 ? 'Subtract ' . $b : 'Add ' . (-$b)), 'op' => ['add', -$b]],
        ]);
        $ops = $this->markOps($ops, "$a*(x+($b))", "x+($b)");
        $arith = $b > 0 ? Fmt::n($q) . ' ' . Fmt::MINUS . ' ' . $b : Fmt::n($q) . ' + ' . (-$b);
        $steps = [
            $this->choice('s1', 'In ' . $eq . ', the ' . $a . ' multiplies the whole group (' . $inner . '). Which operation, done to both sides, undoes that multiplication?', $ops, 'Q06', 'That operation does not undo the multiplication by ' . $a . '.', 'select_one'),
            $this->scalar('s2', 'Divide both sides by ' . $a . '. The left side becomes ' . $inner . '. What does the right side, ' . Fmt::n($c) . ', become?', $q, 'Q06',
                [$this->scalar('s2.1', $a . ' times what number is ' . Fmt::n($c) . '?', $q, 'Q06')]),
            $this->scalar('s3', 'Now ' . $inner . ' = ' . Fmt::n($q) . '. What is x?', $s, 'Q06',
                [$this->scalar('s3.1', 'What is ' . $arith . '?', $s, 'Q06')], null, true),
        ];
        return [$eq, 'Solve ' . $eq . '. What is x?', $steps];
    }

    private function q07Expand(array $p): array
    {
        [$a, $b, $c] = [$p['a'], $p['b'], $p['c']];
        $inner = Fmt::plus('x', $b);
        $eq = $a . '(' . $inner . ') = ' . Fmt::n($c);
        $ab = $a * $b;
        $rest = $c - $ab;
        $s = intdiv($rest, $a);
        $arith = $ab > 0 ? Fmt::n($c) . ' ' . Fmt::MINUS . ' ' . $ab : Fmt::n($c) . ' + ' . (-$ab);
        $steps = [
            $this->expression('s1', 'Rewrite the left side, ' . $a . '(' . $inner . '), without parentheses.', "$a*x+($ab)", ['x'], 'E06', [
                $this->scalar('s1.1', 'What is ' . $a . ' × ' . Fmt::p($b) . '?', $ab, 'E06'),
                $this->expression('s1.2', 'What is ' . $a . ' times x, written as one term?', "$a*x", ['x'], 'E06'),
            ]),
            $this->scalar('s2', 'Now ' . Fmt::plus($a . 'x', $ab) . ' = ' . Fmt::n($c) . '. Undo the ' . Fmt::n($ab) . ' on both sides. What does ' . $a . 'x equal?', $rest, 'Q06',
                [$this->scalar('s2.1', 'What is ' . $arith . '?', $rest, 'Q06')]),
            $this->scalar('s3', 'Now ' . $a . 'x = ' . Fmt::n($rest) . '. What is x?', $s, 'Q06',
                [$this->scalar('s3.1', $a . ' times what number is ' . Fmt::n($rest) . '?', $s, 'Q06')], null, true),
        ];
        return [$eq, 'Solve ' . $eq . '. What is x?', $steps];
    }

    private function e06(array $p, RandomSource $rng): array
    {
        [$a, $b] = [$p['a'], $p['b']];
        $disp = $a . '(x + ' . $b . ')';
        $repeated = $a <= 4
            ? 'What is ' . implode(' + ', array_fill(0, $a, (string) $b)) . '?'
            : 'What is ' . $a . ' × ' . $b . '?';
        $steps = [
            $this->scalar('s1', $disp . ' means ' . $a . ' groups of (x + ' . $b . '). What is the total of the ' . $b . 's in all ' . $a . ' groups?', $a * $b, 'N18',
                [$this->scalar('s1.1', $repeated, $a * $b, 'N18')]),
            $this->expression('s2', 'How many x\'s are in all ' . $a . ' groups? Write them as one term.', "$a*x", ['x'], 'A07',
                [$this->scalar('s2.1', 'If x were 10, what would ' . $a . ' groups of x be worth?', 10 * $a, 'N18')]),
            $this->expression('s3', 'Write ' . $disp . ' without parentheses.', "$a*x+" . ($a * $b), ['x'], 'A07', [
                $this->choice('s3.1', 'Your x-part and your number part are added together inside the parentheses. Which sign joins them in the answer?', $rng->shuffle([
                    ['code' => 'PLUS', 'text' => '+', 'valid' => true],
                    ['code' => 'MINUS', 'text' => Fmt::MINUS, 'valid' => false],
                ]), 'A07', null, 'select_one'),
            ], 'Put the x-part and the number part together. Write ' . $disp . ' without parentheses.', true),
        ];
        return [$disp, 'Write ' . $disp . ' without parentheses.', $steps];
    }

    // ---------------------------------------------------------------- step constructors

    private function scalar(string $id, string $prompt, int|Rational $expected, ?string $implicates, array $smaller = [], ?string $alt = null, bool $final = false): array
    {
        $value = $expected instanceof Rational ? $expected : Rational::fromInt($expected);
        return [
            'id' => $id, 'prompt' => $prompt, 'alt_prompt' => $alt, 'response_type' => 'scalar',
            'answer' => ['kind' => 'exact_rational', 'expected' => $value->toString(), 'requested_form' => 'single_number', 'units' => null, 'tolerance' => null, 'rubric' => []],
            'variables' => ['x'], 'options' => null, 'implicates' => $implicates, 'smaller' => $smaller, 'final' => $final, 'wrong_choice_fact' => null,
        ];
    }

    private function expression(string $id, string $prompt, string $expected, array $vars, ?string $implicates, array $smaller = [], ?string $alt = null, bool $final = false): array
    {
        $canonical = Polynomial::fromNode(ExpressionParser::parse($expected, $vars))->toString();
        return [
            'id' => $id, 'prompt' => $prompt, 'alt_prompt' => $alt, 'response_type' => 'expression',
            'answer' => ['kind' => 'polynomial', 'expected' => $canonical, 'requested_form' => 'expanded', 'units' => null, 'tolerance' => null, 'rubric' => []],
            'variables' => $vars, 'options' => null, 'implicates' => $implicates, 'smaller' => $smaller, 'final' => $final, 'wrong_choice_fact' => null,
        ];
    }

    /** @param list<array{code:string,text:string,valid:bool}> $options */
    private function choice(string $id, string $stem, array $options, ?string $implicates, ?string $wrongFact, string $form): array
    {
        $lettered = CS::letter($options);
        $key = [];
        foreach ($lettered as $i => $o) {
            if ($options[$i]['valid']) {
                $key[] = $o['letter'];
            }
        }
        return [
            'id' => $id, 'prompt' => CS::promptLines($stem, $lettered), 'alt_prompt' => null, 'response_type' => 'classification',
            'answer' => CS::spec($key, $form),
            'variables' => [], 'options' => $lettered, 'implicates' => $implicates, 'smaller' => [], 'final' => false, 'wrong_choice_fact' => $wrongFact,
        ];
    }

    private function signStep(string $id, string $stem, int $value, ?string $implicates, RandomSource $rng): array
    {
        return $this->choice($id, $stem, $rng->shuffle([
            ['code' => 'POS', 'text' => 'Positive', 'valid' => $value > 0],
            ['code' => 'NEG', 'text' => 'Negative', 'valid' => $value < 0],
        ]), $implicates, null, 'select_one');
    }

    private function rename(array $step, string $id): array
    {
        $step['id'] = $id;
        return $step;
    }

    /**
     * Derive which operations are valid by applying each to the left side
     * symbolically and comparing with the target polynomial.
     *
     * @param list<array{code:string,text:string,op:array{0:string,1:int}}> $ops
     * @return list<array{code:string,text:string,valid:bool}>
     */
    private function markOps(array $ops, string $lhs, string $target): array
    {
        $l = Polynomial::fromNode(ExpressionParser::parse($lhs));
        $t = Polynomial::fromNode(ExpressionParser::parse($target));
        $out = [];
        foreach ($ops as $o) {
            [$kind, $k] = $o['op'];
            $kp = Polynomial::constant(Rational::fromInt($k));
            $result = match ($kind) {
                'add' => $l->add($kp),
                'mul' => $l->mul($kp),
                'div' => $l->mul(Polynomial::constant(Rational::of(1, $k))),
            };
            $out[] = ['code' => $o['code'], 'text' => $o['text'], 'valid' => $result->equals($t)];
        }
        return $out;
    }
}
