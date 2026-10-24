<?php
declare(strict_types=1);

namespace AlgebraEngine\Tutor;

use AlgebraEngine\Math\Normalizer;

/**
 * What code does with a typed entry before any AI call (round 6 §3.1, as
 * changed by round 7 §6.3 and §7).
 *
 * - MESSAGE: no number, number word or variable in it; or no problem is open.
 *   Code never classifies typed text as "stuck": "idk" or "???" is a message
 *   to the tutor (round 7 §6.3).
 * - AMBIGUOUS: words, "×", "times", or the variable letter standing alone
 *   between spaces next to a number or another variable ("4 x 4", "4 x x").
 *   Not parsed; the AI reads it.
 * - ANSWER: standard notation code can read, after the safe normalisation (a
 *   capital letter becomes the item's single variable when the entry uses no
 *   other letters; a trailing "?" is ignored when the rest is readable; an
 *   entry that is only a number written in words, such as "twenty five", is
 *   that number).
 * - UNREADABLE: math-like but not readable by code; the AI reads it (round 7
 *   §7.4), and the student never sees a code-written rewrite request.
 *
 * Nothing here is ever a verdict.
 */
final class EntryReader
{
    /** Number words zero to twenty (Numbers reads compounds up to 999). */
    public const NUMBER_WORDS = ['zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve',
        'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen', 'twenty'];

    public const NOTE_CAPITAL = 'capital letter read as the variable';
    public const NOTE_QUESTION_MARK = 'trailing "?" ignored';
    public const NOTE_AI_READING = 'read from the student\'s wording';
    public const NOTE_NUMBER_WORDS = 'number words read as digits';

    /**
     * @param array|null $problem the open problem record, or null
     * @return array{kind:string,read_as:?string,note:?string}
     */
    public static function classify(string $text, ?array $problem): array
    {
        $raw = trim($text);
        if ($problem === null) {
            return self::out('MESSAGE');
        }
        if ($problem['response_type'] === 'classification') {
            if (AnswerCheck::outcome($problem, $raw)->verdict !== 'UNSCORABLE') {
                return self::out('ANSWER', $raw);
            }
            return preg_match('/(?<![\p{L}])[a-dA-D](?![\p{L}])/u', $raw) ? self::out('AMBIGUOUS') : self::out('MESSAGE');
        }
        $vars = $problem['variables'] ?: ['x'];
        if ($problem['answer']['kind'] === 'exact_rational' && ($value = Numbers::phraseValue($raw)) !== null) {
            return self::out('ANSWER', (string) $value, self::NOTE_NUMBER_WORDS);
        }
        if (!self::hasMath($raw, $vars)) {
            return self::out('MESSAGE');
        }
        if ($problem['response_type'] === 'equation' && !str_contains($raw, '=') && self::plainMath($raw, $vars)) {
            // Round 9 §4.5: an entry that is not an equation is a request to rewrite it, never a wrong answer.
            return self::out('NOT_EQUATION');
        }
        if (self::ambiguous($raw, $vars)) {
            return self::out('AMBIGUOUS');
        }
        $candidate = $raw;
        $note = null;
        if (count($vars) === 1) {
            $v = $vars[0];
            preg_match_all('/\p{L}/u', $candidate, $letters);
            $set = array_values(array_unique($letters[0]));
            if (in_array(strtoupper($v), $set, true) && array_diff($set, [$v, strtoupper($v)]) === []) {
                $candidate = str_replace(strtoupper($v), $v, $candidate);
                $note = self::NOTE_CAPITAL;
            }
        }
        $outcome = AnswerCheck::outcome($problem, $candidate);
        if ($outcome->verdict !== 'UNSCORABLE') {
            return self::out('ANSWER', $candidate, $note);
        }
        if (str_ends_with($candidate, '?')) {
            $without = rtrim(rtrim($candidate, '?'));
            if ($without !== '' && AnswerCheck::outcome($problem, $without)->verdict !== 'UNSCORABLE') {
                return self::out('ANSWER', $without, $note === null ? self::NOTE_QUESTION_MARK : $note . '; ' . self::NOTE_QUESTION_MARK);
            }
        }
        return self::out('UNREADABLE');
    }

    /** Only numbers, the variables, operation signs and grouping: a number or an expression standing alone. */
    private static function plainMath(string $text, array $vars): bool
    {
        $t = Normalizer::normalize($text);
        $letters = implode('', array_map(fn ($v) => preg_quote($v, '/') . preg_quote(strtoupper($v), '/'), $vars));
        return preg_match('/^[\d\s.+\-*\/^()' . $letters . ']+$/u', $t) === 1;
    }

    /** A digit, a number word or a standalone variable letter. */
    private static function hasMath(string $text, array $vars): bool
    {
        if (preg_match('/\d/', $text) || Numbers::words($text) !== []) {
            return true;
        }
        foreach ($vars as $v) {
            if (preg_match('/(?<![\p{L}])' . preg_quote($v, '/') . '(?![\p{L}])/iu', $text)) {
                return true;
            }
        }
        return false;
    }

    /** §3.1: words, ×, "times", or the variable letter alone between spaces next to a number or a variable. */
    public static function ambiguous(string $text, array $vars): bool
    {
        if (str_contains($text, "\u{00D7}") || preg_match('/(?<![\p{L}])times(?![\p{L}])/iu', $text)) {
            return true;
        }
        $variableLetters = mb_strtolower(implode('', $vars), 'UTF-8');
        if (preg_match_all('/\p{L}{2,}/u', $text, $m)) {
            foreach ($m[0] as $word) {
                $lower = mb_strtolower($word, 'UTF-8');
                if (strspn($lower, $variableLetters) !== strlen($lower)) {
                    return true;
                }
            }
        }
        foreach ($vars as $v) {
            if (preg_match('/[\d\p{L})]\s+' . preg_quote($v, '/') . '\s+[\d\p{L}(]/iu', $text)) {
                return true;
            }
        }
        return false;
    }

    private static function out(string $kind, ?string $readAs = null, ?string $note = null): array
    {
        return ['kind' => $kind, 'read_as' => $readAs, 'note' => $note];
    }
}
