<?php
declare(strict_types=1);

namespace AlgebraEngine\Workflow;

/**
 * Round 6 (architecture 1.3, §3.4): the host's check on a model reading of a
 * learner entry. A reading may only re-express what the learner wrote:
 * - every number in it appears in the entry, as digits or as a number word
 *   from the host list (zero to twenty);
 * - a negative value needs a minus sign, or the word negative or minus, in the entry;
 * - it introduces no variable the entry does not contain (reading an x used as
 *   a multiplication sign as "times" removes a variable; it never adds one);
 * - for a choice item, every option letter in it appears in the entry.
 * Whether the item's checker can read it is checked by the engine.
 */
final class ReadingGuard
{
    /**
     * @param string $entry everything the learner wrote for this reading (the entry, or the entry and a clarification reply)
     * @param list<string> $variables the item's variables
     * @return list<string> reasons; empty when the reading may be scored
     */
    public static function errors(string $entry, string $reading, array $variables, string $responseType): array
    {
        $errors = [];
        $have = self::entryNumbers($entry);
        foreach (self::numbers($reading) as $n) {
            if (!isset($have[$n])) {
                $errors[] = 'the reading uses the number ' . $n . ', which is not in what the learner wrote';
            }
        }
        if (self::negative($reading) && !self::entryHasMinus($entry)) {
            $errors[] = 'the reading is negative, but what the learner wrote has no minus sign or the word negative or minus';
        }
        $entryLetters = self::letters(self::withoutWords($entry));
        if ($responseType === 'classification') {
            foreach (self::letters($reading) as $l) {
                if (!in_array($l, $entryLetters, true)) {
                    $errors[] = 'the reading chooses option ' . strtoupper($l) . ', which the learner did not write';
                }
            }
            return $errors;
        }
        foreach (self::letters($reading) as $l) {
            if (!in_array($l, $variables, true)) {
                $errors[] = 'the reading uses the letter ' . $l . ', which is not a variable of this question';
            } elseif (!in_array($l, $entryLetters, true)) {
                $errors[] = 'the reading introduces the variable ' . $l . ', which the learner did not write';
            }
        }
        return array_values(array_unique($errors));
    }

    /** @return array<string,true> whole numbers written in the entry, as digits or number words */
    public static function entryNumbers(string $entry): array
    {
        $out = [];
        foreach (self::numbers($entry) as $n) {
            $out[$n] = true;
        }
        $lower = mb_strtolower($entry, 'UTF-8');
        foreach (EntryInterpreter::NUMBER_WORDS as $value => $word) {
            if (preg_match('/(?<![\p{L}])' . $word . '(?![\p{L}])/u', $lower) === 1) {
                $out[(string) $value] = true;
            }
        }
        return $out;
    }

    /** @return list<string> unsigned whole numbers written with digits (a fraction 5/2 gives 5 and 2; a decimal 2.5 gives 2.5) */
    public static function numbers(string $text): array
    {
        preg_match_all('/\d+(?:\.\d+)?/', $text, $m);
        return array_values(array_unique(array_map(fn ($n) => str_contains($n, '.') ? rtrim(rtrim($n, '0'), '.') : (string) (int) $n, $m[0])));
    }

    private static function negative(string $reading): bool
    {
        $s = str_replace(["\u{2212}", ' '], ['-', ''], $reading);
        return preg_match('/(^|[=(*\/])-/', $s) === 1;
    }

    private static function entryHasMinus(string $entry): bool
    {
        return preg_match('/[-\x{2212}]|\b(negative|minus)\b/iu', $entry) === 1;
    }

    /** Single letters used as symbols. @return list<string> */
    private static function letters(string $text): array
    {
        preg_match_all('/\p{L}/u', mb_strtolower($text, 'UTF-8'), $m);
        return array_values(array_unique($m[0]));
    }

    /** The entry with its words (runs of two or more letters) removed, so "because" does not supply a variable. */
    private static function withoutWords(string $entry): string
    {
        return (string) preg_replace('/\p{L}{2,}/u', ' ', $entry);
    }
}
