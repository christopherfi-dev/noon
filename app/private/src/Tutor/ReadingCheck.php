<?php
declare(strict_types=1);

namespace AlgebraEngine\Tutor;

use AlgebraEngine\Math\Normalizer;

/**
 * Round 6 §4.1: the AI's reading of a student entry may only re-express what
 * the student wrote.
 * - Every number in the reading appears in the entry, as digits or as a
 *   number word from the existing list.
 * - It introduces no variable: each letter in the reading is a variable of
 *   the problem that the entry itself contains as a letter on its own (an
 *   "x" the student used for multiplication may be read as ×, never added).
 * A failing reading is rejected and the student is asked to rewrite the entry.
 */
final class ReadingCheck
{
    public const MAX_LENGTH = 80;

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
        return $out;
    }
}
