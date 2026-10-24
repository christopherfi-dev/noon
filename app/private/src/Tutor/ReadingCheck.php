<?php
declare(strict_types=1);

namespace AlgebraEngine\Tutor;

use AlgebraEngine\Math\Normalizer;

/**
 * The AI's reading of a student entry may only re-express what the student
 * wrote (architecture v1.8 §4 and §7; round 6 §4.1; round 7 §7.1).
 *
 * - Every number in the reading appears in the entry, as digits or as number
 *   words (compounds up to 999).
 * - It introduces no variable: each letter in the reading is a variable of
 *   the problem that the entry itself contains as a letter on its own (an
 *   "x" the student used for multiplication may be read as ×, never added).
 * - Drop check: the reading must re-express one stretch of the entry's
 *   mathematics token for token, with operation words read as signs, number
 *   words as digits, "x" between two factors as ×, and implicit
 *   multiplication equal to "*". Whatever it leaves out must be separated
 *   from that stretch by words or punctuation: content joined to it by an
 *   operation sign, an operation word (plus, minus, times, divided by, over,
 *   negative), implicit multiplication or grouping may not be left out.
 * - A reading that changes grouping or the order of operands matches no
 *   stretch and is rejected. The two sides of "=" may be swapped.
 *
 * Code declares this scope only: a stretch joined to the rest by "=" or a
 * relation word ("is", "equals") may be left out, because the round 7 rule
 * names operations only (see ROUND_7_REPORT.md).
 */
final class ReadingCheck
{
    public const MAX_LENGTH = 80;
    private const MAX_TOKENS = 60;
    private const MAX_AMBIGUOUS = 8;

    /**
     * @param list<string> $variables the open problem's variables
     * @return list<string> reasons; empty when the reading is acceptable
     */
    public static function check(string $entry, string $reading, array $variables): array
    {
        $reading = trim($reading);
        if ($reading === '' || mb_strlen($reading) > self::MAX_LENGTH) {
            return ['the reading must be 1 to ' . self::MAX_LENGTH . ' characters'];
        }
        $plain = Normalizer::normalize($reading);
        if (preg_match('/[^0-9a-zA-Z+\-*\/^().=, ]/', $plain)) {
            return ['the reading may contain only numbers, the problem\'s variables and + - * / ^ ( ) ='];
        }
        $out = [];
        $inEntry = Numbers::all($entry);
        $added = array_values(array_diff(Numbers::digits($plain), $inEntry));
        if ($added !== []) {
            $out[] = 'the reading adds numbers the student did not write: ' . implode(', ', $added);
        }
        preg_match_all('/[a-zA-Z]/', $plain, $m);
        foreach (array_values(array_unique(array_map('strtolower', $m[0]))) as $letter) {
            if (!in_array($letter, $variables, true)) {
                $out[] = 'the reading introduces the letter ' . $letter . ', which is not a variable of this problem';
            } elseif (!preg_match('/(?<![\p{L}])' . $letter . '(?![\p{L}])/iu', $entry)) {
                $out[] = 'the reading introduces the variable ' . $letter . ', which the student did not write';
            }
        }
        if ($out !== []) {
            return $out;
        }
        return self::dropCheck($entry, $plain, $variables);
    }

    /** @return list<string> */
    public static function dropCheck(string $entry, string $reading, array $variables): array
    {
        $e = self::tokens($entry, $variables, true);
        $r = self::tokens($reading, $variables, false);
        if (count($e) > self::MAX_TOKENS || $r === [] || array_filter($r, fn ($t) => !self::isMath($t)) !== []) {
            return ['code cannot compare this reading with the entry'];
        }
        $targets = [self::canonical($r)];
        $eq = array_keys(array_filter($r, fn ($t) => $t['t'] === 'REL'));
        if (count($eq) === 1) {
            $i = $eq[0];
            $targets[] = self::canonical(array_merge(array_slice($r, $i + 1), [$r[$i]], array_slice($r, 0, $i)));
        }
        $joined = null;
        $longest = 0;
        $n = count($e);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j <= $n && self::isMath($e[$j - 1]); $j++) {
                if (!self::matches(array_slice($e, $i, $j - $i), $targets)) {
                    continue;
                }
                $left = $i > 0 && self::joins($e[$i - 1]) ? $i - 1 : null;
                $right = $j < $n && self::joins($e[$j]) ? $j : null;
                if ($left === null && $right === null) {
                    return [];
                }
                if ($j - $i > $longest) {
                    $longest = $j - $i;
                    $joined = self::leftOut($e, $left ?? $right, $left !== null ? -1 : 1);
                }
            }
        }
        if ($joined !== null) {
            return ['the reading leaves out "' . $joined . '", which the student joined to the answer with an operation or grouping'];
        }
        return ['the reading does not re-express what the student wrote: it changes the grouping, the order or the operations of the entry'];
    }

    /**
     * Tokens: NUM (value), VAR, AMBX (the letter x, which a student may use as
     * times), OP (+ - / ^), MUL, REL (=), LP, RP, WORD, PUNCT.
     *
     * @return list<array{t:string,v:string,s:string}>
     */
    public static function tokens(string $text, array $variables, bool $entry): array
    {
        $lower = mb_strtolower(Normalizer::normalize($text), 'UTF-8');
        $numbers = [];
        foreach (Numbers::phrases($lower) as $p) {
            $numbers[$p['start']] = $p;
        }
        preg_match_all('/\d+(?:\.\d+)?|\p{L}+|[+\-*\/^=()\[\]{}]|[^\s\d\p{L}]/u', $lower, $m, PREG_OFFSET_CAPTURE);
        $raw = [];
        $skipUntil = -1;
        foreach ($m[0] as [$s, $at]) {
            if ($at < $skipUntil) {
                continue;
            }
            if (isset($numbers[$at])) {
                $raw[] = ['t' => 'NUM', 'v' => (string) $numbers[$at]['value'], 's' => $numbers[$at]['text']];
                $skipUntil = $numbers[$at]['end'];
                continue;
            }
            $raw[] = self::classify($s, $variables, $entry);
        }
        $out = [];
        for ($i = 0, $n = count($raw); $i < $n; $i++) {
            $t = $raw[$i];
            $next = $raw[$i + 1]['s'] ?? '';
            if ($t['t'] === 'WORD' && in_array($t['s'], ['multiplied', 'divided'], true) && $next === 'by') {
                $out[] = $t['s'] === 'multiplied' ? ['t' => 'MUL', 'v' => '*', 's' => 'multiplied by'] : ['t' => 'OP', 'v' => '/', 's' => 'divided by'];
                $i++;
                continue;
            }
            if ($t['t'] === 'WORD' && $t['s'] === 'is' && $next === 'equal' && ($raw[$i + 2]['s'] ?? '') === 'to') {
                $out[] = ['t' => 'REL', 'v' => '=', 's' => 'is equal to'];
                $i += 2;
                continue;
            }
            if ($t['t'] === 'WORD' && $t['s'] === 'equal' && $next === 'to') {
                $out[] = ['t' => 'REL', 'v' => '=', 's' => 'equal to'];
                $i++;
                continue;
            }
            $out[] = $t;
        }
        // "is" between two pieces of mathematics is the relation; elsewhere it is a word.
        foreach ($out as $k => $t) {
            if ($t['t'] === 'WORD' && $t['s'] === 'is' && isset($out[$k - 1], $out[$k + 1]) && self::isMath($out[$k - 1]) && self::isMath($out[$k + 1])) {
                $out[$k] = ['t' => 'REL', 'v' => '=', 's' => 'is'];
            }
        }
        return $out;
    }

    private static function classify(string $s, array $variables, bool $entry): array
    {
        $words = ['plus' => ['OP', '+'], 'minus' => ['OP', '-'], 'negative' => ['OP', '-'], 'times' => ['MUL', '*'], 'over' => ['OP', '/'], 'equals' => ['REL', '=']];
        if (preg_match('/^\d/', $s)) {
            return ['t' => 'NUM', 'v' => Numbers::canonical($s), 's' => $s];
        }
        if (isset($words[$s])) {
            return ['t' => $words[$s][0], 'v' => $words[$s][1], 's' => $s];
        }
        if (preg_match('/^\p{L}$/u', $s)) {
            if ($entry && $s === 'x') {
                return ['t' => in_array('x', $variables, true) ? 'AMBX' : 'MULX', 'v' => 'x', 's' => $s];
            }
            if (in_array($s, $variables, true)) {
                return ['t' => 'VAR', 'v' => $s, 's' => $s];
            }
            return ['t' => 'WORD', 'v' => $s, 's' => $s];
        }
        if (preg_match('/^\p{L}+$/u', $s)) {
            return ['t' => 'WORD', 'v' => $s, 's' => $s];
        }
        return match ($s) {
            '+', '-', '/', '^' => ['t' => 'OP', 'v' => $s, 's' => $s],
            '*' => ['t' => 'MUL', 'v' => '*', 's' => $s],
            '=' => ['t' => 'REL', 'v' => '=', 's' => $s],
            '(', '[', '{' => ['t' => 'LP', 'v' => '(', 's' => $s],
            ')', ']', '}' => ['t' => 'RP', 'v' => ')', 's' => $s],
            default => ['t' => 'PUNCT', 'v' => $s, 's' => $s],
        };
    }

    private static function isMath(array $t): bool
    {
        return !in_array($t['t'], ['WORD', 'PUNCT'], true);
    }

    /** A neighbour that joins left-out content to the answer: anything mathematical except a relation. */
    private static function joins(array $t): bool
    {
        return self::isMath($t) && $t['t'] !== 'REL';
    }

    /** The left-out text from the joining token outward, up to the next word or punctuation. */
    private static function leftOut(array $e, int $from, int $dir): string
    {
        $parts = [];
        for ($k = $from; isset($e[$k]) && self::isMath($e[$k]); $k += $dir) {
            $parts[] = $e[$k]['s'];
        }
        return implode(' ', $dir < 0 ? array_reverse($parts) : $parts);
    }

    /** True when some reading of the ambiguous x's in $span gives one of $targets. */
    private static function matches(array $span, array $targets): bool
    {
        $amb = array_keys(array_filter($span, fn ($t) => $t['t'] === 'AMBX'));
        if (count($amb) > self::MAX_AMBIGUOUS) {
            return false;
        }
        for ($mask = 0; $mask < (1 << count($amb)); $mask++) {
            $tokens = $span;
            foreach ($amb as $bit => $k) {
                $tokens[$k] = ($mask >> $bit) & 1 ? ['t' => 'MULX', 'v' => 'x', 's' => 'x'] : ['t' => 'VAR', 'v' => 'x', 's' => 'x'];
            }
            $c = self::canonical($tokens);
            if ($c !== null && in_array($c, $targets, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * One canonical string: explicit and implicit multiplication are the same
     * mark; an "x" read as times needs a factor on each side.
     */
    private static function canonical(array $tokens): ?string
    {
        $out = [];
        $n = count($tokens);
        foreach ($tokens as $k => $t) {
            if ($t['t'] === 'MULX') {
                $prev = $tokens[$k - 1]['t'] ?? null;
                $next = $tokens[$k + 1]['t'] ?? null;
                if (!in_array($prev, ['NUM', 'VAR', 'RP'], true) || !in_array($next, ['NUM', 'VAR', 'LP', 'AMBX'], true) || $k === 0 || $k === $n - 1) {
                    return null;
                }
                $out[] = 'M';
                continue;
            }
            if ($k > 0 && in_array($tokens[$k - 1]['t'], ['NUM', 'VAR', 'RP'], true) && in_array($t['t'], ['VAR', 'LP'], true)) {
                $out[] = 'M';
            }
            $out[] = match ($t['t']) {
                'NUM' => 'N' . $t['v'],
                'VAR' => 'V' . $t['v'],
                'MUL' => 'M',
                'OP' => 'O' . $t['v'],
                'REL' => '=',
                'LP' => '(',
                'RP' => ')',
                default => '?' . $t['v'],
            };
        }
        // "2*x" and "2x" are the same; collapse doubled marks from "2 * x" read with an implicit mark.
        return (string) preg_replace('/(M\|)+/', 'M|', implode('|', $out) . '|');
    }
}
