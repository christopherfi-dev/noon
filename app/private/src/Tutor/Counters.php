<?php
declare(strict_types=1);

namespace AlgebraEngine\Tutor;

use AlgebraEngine\Math\Ast\BinOp;
use AlgebraEngine\Math\Ast\Group;
use AlgebraEngine\Math\Ast\Neg;
use AlgebraEngine\Math\Ast\Node;
use AlgebraEngine\Math\Ast\Num;
use AlgebraEngine\Math\Ast\Pow;
use AlgebraEngine\Math\Ast\VarRef;
use AlgebraEngine\Math\EquationParser;
use AlgebraEngine\Math\ExpressionParser;
use AlgebraEngine\Math\LinearEquation;
use AlgebraEngine\Math\MathInputError;
use AlgebraEngine\Math\Normalizer;
use AlgebraEngine\Math\Polynomial;
use AlgebraEngine\Math\Rational;
use AlgebraEngine\Math\ScalarParser;
use AlgebraEngine\Support\ProblemText;

/**
 * Round 8 §3.7: two counters on the tutor's learner-visible text. They log
 * only; they never block, repair, hold or change anything.
 *
 * RESOLVE_AFTER_CORRECT: feedback on a correct answer that contains an
 * intermediate step the tutor supplied: an equation or value on the way from
 * the problem to its answer, other than the problem, the student's answer and
 * the student's own working.
 *
 * VALUE_QUESTION_IN_TEXT: a question in student_message that asks for a
 * number, expression or equation while item is null.
 *
 * Round 9 §4.6: RESOLVE_AFTER_CORRECT leaves out substitution checks (a side
 * of the problem with the answer put in for x, as in "4·10 + 6 = 46 checks
 * out", and the rest of that chain); VALUE_QUESTION_IN_TEXT also counts a
 * request for an expression ("what is … written as", "how would you write …
 * as …") and leaves out a question that asks for an operation ("what is the
 * opposite of adding 2?").
 *
 * HEURISTIC, declared scope: equations are the runs of mathematical text
 * that contain "="; values are digits only, for linear equations only, and
 * are the values of the problem's sub-expressions at its solution (and of the
 * products a distribution makes), other than 0, 1, the problem's numbers, its
 * answer and the student's numbers. Value questions are found by wording
 * (question sentences with a value cue and some mathematics in them). Each
 * hit is logged with the exact text so false hits can be seen.
 */
final class Counters
{
    /**
     * @param array $problem the answered problem's record (kind, prompt, answer, variables, numbers)
     * @param list<string> $student the student's entry and own working
     * @return list<string> the intermediate steps found, as written in the text
     */
    public static function resolveAfterCorrect(string $text, array $problem, array $student): array
    {
        if (($problem['answer']['kind'] ?? '') === 'classification') {
            return [];
        }
        $vars = $problem['variables'] ?: ['x'];
        $studentFlat = array_map([self::class, 'flat'], $student);
        $main = ProblemText::main($problem['prompt']);
        $problemSides = $main === '' ? [] : array_map('trim', explode('=', $main));
        $answerFlat = self::flat((string) $problem['answer']['expected']);
        $hits = [];
        $checks = [];
        foreach (ProblemText::runs($text) as $run) {
            if (!str_contains($run, '=')) {
                continue;
            }
            $sides = array_map('trim', explode('=', $run));
            if (self::substitutionCheck($sides, $problem)) {
                $checks[] = $run;
                continue;
            }
            for ($i = 0; $i + 1 < count($sides); $i++) {
                [$l, $r] = [$sides[$i], $sides[$i + 1]];
                if ($l === '' || $r === '') {
                    continue;
                }
                $statement = $l . ' = ' . $r;
                $flat = self::flat($statement);
                if (self::inStudent($flat, $studentFlat)) {
                    continue;
                }
                if (self::isStep($l, $r, $problem, $vars, $problemSides, $answerFlat, $studentFlat)) {
                    $hits[] = $statement;
                }
            }
        }
        $rest = str_replace($checks, ' ', $text);
        foreach (self::values($problem, $student) as $value) {
            if (in_array($value, Numbers::digits($rest), true)) {
                $hits[] = 'value ' . $value;
            }
        }
        return array_values(array_unique($hits));
    }

    /** @return list<string> the question sentences in $text that ask for a number, expression or equation */
    public static function valueQuestions(string $text): array
    {
        $hits = [];
        foreach (TextGuard::sentences($text) as $sentence) {
            foreach (self::questionClauses($sentence) as $q) {
                if (self::asksForValue($q)) {
                    $hits[] = $q;
                }
            }
        }
        return array_values(array_unique($hits));
    }

    // ------------------------------------------------------------ resolve after correct

    /**
     * A chain of equal sides in which one side is a side of the problem (a
     * linear equation) with the answer put in for x: a check by substitution,
     * not a step on the way to the answer.
     *
     * @param list<string> $sides
     */
    private static function substitutionCheck(array $sides, array $problem): bool
    {
        if ($problem['kind'] !== 'linear_equation') {
            return false;
        }
        try {
            $patterns = EquationParser::parse(Normalizer::normalize(ProblemText::main($problem['prompt'])), ['x']);
            $x0 = ScalarParser::parse((string) $problem['answer']['expected']);
        } catch (MathInputError) {
            return false;
        }
        foreach ($sides as $side) {
            try {
                $n = ExpressionParser::parse(Normalizer::normalize($side), ['x']);
            } catch (MathInputError) {
                continue;
            }
            foreach ($patterns as $pattern) {
                if (self::hasVar($pattern) && self::substituted($n, $pattern, $x0)) {
                    return true;
                }
            }
        }
        return false;
    }

    /** True when $n is $pattern with each x written as the number $x0. */
    private static function substituted(Node $n, Node $pattern, Rational $x0): bool
    {
        if ($pattern instanceof VarRef) {
            $v = self::hasVar($n) ? null : self::literal($n);
            return $v !== null && $v->equals($x0);
        }
        if ($n instanceof Group && !($pattern instanceof Group)) {
            return self::substituted($n->inner, $pattern, $x0);
        }
        if ($pattern instanceof Group) {
            return self::substituted($n instanceof Group ? $n->inner : $n, $pattern->inner, $x0);
        }
        return match (true) {
            $pattern instanceof Num => $n instanceof Num && $n->value->equals($pattern->value),
            $pattern instanceof Neg => $n instanceof Neg && self::substituted($n->operand, $pattern->operand, $x0),
            $pattern instanceof BinOp => $n instanceof BinOp && $n->op === $pattern->op
                && self::substituted($n->left, $pattern->left, $x0) && self::substituted($n->right, $pattern->right, $x0),
            $pattern instanceof Pow => $n instanceof Pow && $n->exponent === $pattern->exponent && self::substituted($n->base, $pattern->base, $x0),
            default => false,
        };
    }

    /** The value of a number written alone: 10, −8, (−8). */
    private static function literal(Node $n): ?Rational
    {
        return match (true) {
            $n instanceof Num => $n->value,
            $n instanceof Neg => self::literal($n->operand)?->neg(),
            $n instanceof Group => self::literal($n->inner),
            default => null,
        };
    }

    private static function isStep(string $l, string $r, array $problem, array $vars, array $problemSides, string $answerFlat, array $studentFlat): bool
    {
        try {
            $ln = ExpressionParser::parse(Normalizer::normalize($l), $vars);
            $rn = ExpressionParser::parse(Normalizer::normalize($r), $vars);
            $lp = Polynomial::fromNode($ln);
            $rp = Polynomial::fromNode($rn);
        } catch (MathInputError) {
            return false;
        }
        $isProblemSide = fn (string $s) => in_array(self::flat($s), array_map([self::class, 'flat'], $problemSides), true);
        $isAnswer = fn (string $s) => self::flat($s) === $answerFlat || self::inStudentExact(self::flat($s), $studentFlat);
        $hasVar = $lp->variables() !== [] || $rp->variables() !== [];
        if (!$hasVar) {
            // A numeric statement: arithmetic on the way, unless false or the problem restated with its answer.
            $lv = $lp->constantValue();
            $rv = $rp->constantValue();
            if ($lv === null || $rv === null || !$lv->equals($rv)) {
                return false;
            }
            return !(($isProblemSide($l) && $isAnswer($r)) || ($isProblemSide($r) && $isAnswer($l)));
        }
        if ($problem['kind'] === 'linear_equation') {
            try {
                $sol = LinearEquation::solve($ln, $rn);
                $answer = ScalarParser::parse((string) $problem['answer']['expected']);
            } catch (MathInputError) {
                return false;
            }
            if ($sol->kind !== 'UNIQUE' || $sol->value === null || !$sol->value->equals($answer)) {
                return false;
            }
            $bare = fn (Node $n) => ($n instanceof Group ? $n->inner : $n) instanceof VarRef;
            if ($bare($ln) || $bare($rn)) {
                return false; // "x = 6": the answer
            }
            $problemFlat = array_map([self::class, 'flat'], $problemSides);
            $same = count($problemFlat) === 2 && ([self::flat($l), self::flat($r)] === $problemFlat || [self::flat($r), self::flat($l)] === $problemFlat);
            return !$same;
        }
        if ($problem['kind'] === 'expand') {
            if (!$lp->equals($rp)) {
                return false;
            }
            // An identity: a step when one side is neither the problem's expression nor the answer as written.
            foreach ([$l, $r] as $side) {
                if (!$isProblemSide($side) && !$isAnswer($side)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Values on the way from a linear equation to its answer: each
     * sub-expression's value at the solution, and the products a distribution
     * makes. @return list<string> absolute values as digit strings
     */
    private static function values(array $problem, array $student): array
    {
        if ($problem['kind'] !== 'linear_equation') {
            return [];
        }
        $main = ProblemText::main($problem['prompt']);
        try {
            [$l, $r] = EquationParser::parse(Normalizer::normalize($main), ['x']);
            $x0 = ScalarParser::parse((string) $problem['answer']['expected']);
        } catch (MathInputError) {
            return [];
        }
        $found = [];
        foreach ([$l, $r] as $side) {
            self::collect($side, $x0, $found, true);
        }
        $exclude = array_merge($problem['numbers'], [(string) abs($x0->num()), '0', '1']);
        foreach ($student as $s) {
            $exclude = array_merge($exclude, Numbers::digits($s));
        }
        $out = [];
        foreach ($found as $v) {
            if (!$v->isInteger()) {
                continue;
            }
            $abs = (string) abs($v->num());
            if (!in_array($abs, $exclude, true)) {
                $out[] = $abs;
            }
        }
        return array_values(array_unique($out));
    }

    /** @param list<Rational> $found */
    private static function collect(Node $n, Rational $x0, array &$found, bool $top): void
    {
        if (!$top && self::hasVar($n) && !($n instanceof VarRef) && ($v = self::evaluate($n, $x0)) !== null) {
            $found[] = $v;
        }
        if ($n instanceof BinOp && $n->op === '*') {
            // a(x + b): a·x and a·b
            foreach ([[$n->left, $n->right], [$n->right, $n->left]] as [$k, $g]) {
                $inner = $g instanceof Group ? $g->inner : null;
                $kv = self::evaluate($k, $x0);
                if ($inner !== null && $kv !== null && !self::hasVar($k)) {
                    foreach (self::terms($inner) as $term) {
                        $tv = self::evaluate($term, $x0);
                        if ($tv !== null) {
                            $found[] = $kv->mul($tv);
                        }
                    }
                }
            }
        }
        foreach (self::children($n) as $c) {
            self::collect($c, $x0, $found, false);
        }
    }

    /** @return list<Node> the terms of a sum */
    private static function terms(Node $n): array
    {
        if ($n instanceof BinOp && ($n->op === '+' || $n->op === '-')) {
            $right = $n->op === '-' ? new Neg($n->right) : $n->right;
            return array_merge(self::terms($n->left), [$right]);
        }
        return [$n];
    }

    /** @return list<Node> */
    private static function children(Node $n): array
    {
        return match (true) {
            $n instanceof BinOp => [$n->left, $n->right],
            $n instanceof Group => [$n->inner],
            $n instanceof Neg => [$n->operand],
            $n instanceof Pow => [$n->base],
            default => [],
        };
    }

    private static function hasVar(Node $n): bool
    {
        return $n instanceof VarRef || array_filter(self::children($n), [self::class, 'hasVar']) !== [];
    }

    private static function evaluate(Node $n, Rational $x0): ?Rational
    {
        try {
            return match (true) {
                $n instanceof Num => $n->value,
                $n instanceof VarRef => $x0,
                $n instanceof Group => self::evaluate($n->inner, $x0),
                $n instanceof Neg => self::evaluate($n->operand, $x0)?->neg(),
                $n instanceof Pow => self::power($n, $x0),
                $n instanceof BinOp => self::binary($n, $x0),
                default => null,
            };
        } catch (\Throwable) {
            return null;
        }
    }

    private static function power(Pow $n, Rational $x0): ?Rational
    {
        $b = self::evaluate($n->base, $x0);
        if ($b === null || $n->exponent < 0 || $n->exponent > 6) {
            return null;
        }
        $v = Rational::fromInt(1);
        for ($i = 0; $i < $n->exponent; $i++) {
            $v = $v->mul($b);
        }
        return $v;
    }

    private static function binary(BinOp $n, Rational $x0): ?Rational
    {
        $a = self::evaluate($n->left, $x0);
        $b = self::evaluate($n->right, $x0);
        if ($a === null || $b === null) {
            return null;
        }
        return match ($n->op) {
            '+' => $a->add($b),
            '-' => $a->sub($b),
            '*' => $a->mul($b),
            '/' => $b->isZero() ? null : $a->div($b),
            default => null,
        };
    }

    /** Mathematical text with spaces removed and multiplication written the same way ("3 × (x + 4)" and "3(x+4)" agree). */
    private static function flat(string $s): string
    {
        $s = mb_strtolower(Normalizer::normalize($s), 'UTF-8');
        $s = (string) preg_replace('/\s+/u', '', $s);
        $s = (string) preg_replace('/\*(?=[(\p{L}])|(?<=[)])\*/u', '', $s);
        return $s;
    }

    /** @param list<string> $studentFlat */
    private static function inStudent(string $flat, array $studentFlat): bool
    {
        foreach ($studentFlat as $s) {
            if ($flat !== '' && str_contains($s, $flat)) {
                return true;
            }
        }
        return false;
    }

    /** @param list<string> $studentFlat */
    private static function inStudentExact(string $flat, array $studentFlat): bool
    {
        foreach ($studentFlat as $s) {
            if ($flat !== '' && ($s === $flat || str_ends_with($s, '=' . $flat) || str_starts_with($s, $flat . '='))) {
                return true;
            }
        }
        return false;
    }

    // ------------------------------------------------------------ value questions

    /** @return list<string> the question parts of a sentence (the text before each "?") */
    private static function questionClauses(string $sentence): array
    {
        if (!str_contains($sentence, '?')) {
            return [];
        }
        $out = [];
        foreach (explode('?', $sentence) as $i => $part) {
            if ($i === substr_count($sentence, '?')) {
                break; // the text after the last "?" is not a question
            }
            $part = trim($part);
            if ($part !== '') {
                // A question introduced after a colon or semicolon stands on its own.
                $pieces = preg_split('/[:;]\s+/u', $part) ?: [$part];
                $out[] = trim((string) end($pieces)) . '?';
            }
        }
        return $out;
    }

    private static function asksForValue(string $q): bool
    {
        $l = mb_strtolower(Normalizer::normalize(str_replace("\u{2019}", "'", $q)), 'UTF-8');
        $math = preg_match('/\d|(?<![\p{L}])x(?![\p{L}])|=/u', $l) === 1;
        // Round 9 §4.6: a request for an expression counts.
        if (preg_match('/\bwhat\s+(?:is|\'s|are|was|would|does)\b.*\bwritten\s+as\b|\bhow\s+(?:would|could|can|do|does|did|might|should|will)\s+(?:you|we)\s+(?:write|rewrite|express)\b.*\bas\b/u', $l)) {
            return true;
        }
        if (preg_match('/^\W*(?:why\b|how\s+(?:did|do|does|would|could|can|might)\b|do\s+you\b|did\s+you\b|would\s+you\b|are\s+you\b|can\s+you\s+explain\b|could\s+you\s+explain\b|does\s+that\b|is\s+that\b|ready\b|want\b|shall\b)/u', $l)) {
            return false;
        }
        if (preg_match('/=\s*(?:\?|_+)/u', $l)) {
            return true;
        }
        if (preg_match('/\bwhat\s+(?:number|value|expression|equation|term|result|answer|sum|product|difference|quotient|total)\b/u', $l)) {
            return true;
        }
        if (preg_match('/\bhow\s+(?:much|many)\b/u', $l)) {
            return $math;
        }
        $action = '/\bwhat\s+(?:is|\'s|are|was)\s+(?:the\s+|a\s+|your\s+)?(?:first\s+|next\s+|last\s+|same\s+|other\s+|best\s+)?(?:step|operation|rule|property|idea|reason|thing|way|method|strategy|pattern|mistake|difference\s+between|connection|goal)\b'
            . '|\bwhat\s+(?:do|did|would|should|could|can|will|might)\s+(?:you|we)\s+(?:do|notice|think|see|try|need\s+to\s+do|want|remember)\b|\bwhat\s+happens\b|\bwhat\s+(?:operation|step|rule|property)\b'
            . '|\bwhat\s+(?:is|\'s|are)\s+(?:being|happening|done|attached)\b'
            . '|\bwhat\s+(?:is|\'s|was)\s+the\s+(?:opposite|inverse|reverse)\s+(?:operation\s+)?(?:of|to)\b|\b(?:what|which)\s+(?:single\s+|one\s+)?operations?\b/u';
        if (preg_match($action, $l)) {
            return false;
        }
        if (preg_match('/\bwhat\s+(?:is|\'s|are|would|will|does|do|did|must|should|could|comes?|gives?|makes?)\b/u', $l)) {
            return $math;
        }
        if (preg_match('/\b(?:find|calculate|compute|solve|simplify|expand|work\s+out|figure\s+out|evaluate|rewrite|write)\b/u', $l)) {
            return $math;
        }
        return false;
    }
}
