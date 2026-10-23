<?php
declare(strict_types=1);

namespace AlgebraEngine\Tutor;

use AlgebraEngine\Math\Normalizer;

/**
 * Numbers written in a piece of text, as digits or as number words from the
 * existing list (zero to twenty). Used by the reading check (§4.1), the
 * problem checks (§4.2) and the check-card rule (§5.2).
 */
final class Numbers
{
    /**
     * Absolute numeric literals written with digits, normalised: "007" → "7",
     * "2.50" → "2.5". A sign is not part of a number; "3/4" gives 3 and 4.
     *
     * @return list<string>
     */
    public static function digits(string $text): array
    {
        $text = Normalizer::normalize($text);
        preg_match_all('/(?<![\d.])\d+(?:\.\d+)?(?![\d])/', $text, $m);
        return array_values(array_unique(array_map([self::class, 'canonical'], $m[0])));
    }

    public static function canonical(string $literal): string
    {
        if (str_contains($literal, '.')) {
            [$w, $f] = explode('.', $literal, 2);
            $f = rtrim($f, '0');
            $w = ltrim($w, '0');
            return ($w === '' ? '0' : $w) . ($f === '' ? '' : '.' . $f);
        }
        $t = ltrim($literal, '0');
        return $t === '' ? '0' : $t;
    }

    /**
     * Values written as number words (zero to twenty). "One" used as a
     * determiner or pronoun ("one way", "each one") is not the value 1.
     *
     * @return list<int>
     */
    public static function words(string $text): array
    {
        $lower = mb_strtolower($text, 'UTF-8');
        $out = [];
        foreach (EntryReader::NUMBER_WORDS as $value => $word) {
            if (!preg_match_all('/(?<![\p{L}])' . $word . '(?![\p{L}])/u', $lower, $m, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($m[0] as [$found, $offset]) {
                if ($value === 1 && self::oneAsDeterminer($lower, $offset, $offset + strlen($found))) {
                    continue;
                }
                $out[] = $value;
                break;
            }
        }
        return $out;
    }

    private const TENS = ['twenty' => 20, 'thirty' => 30, 'forty' => 40, 'fifty' => 50, 'sixty' => 60, 'seventy' => 70, 'eighty' => 80, 'ninety' => 90];

    /**
     * Number words beyond the existing list, for detection only (the
     * check-card rule): the tens and compounds such as "twenty-eight". The
     * reading check does not use these; it accepts only the existing list.
     *
     * @return list<int>
     */
    public static function wordsExtended(string $text): array
    {
        $lower = mb_strtolower($text, 'UTF-8');
        $out = self::words($text);
        $units = array_slice(EntryReader::NUMBER_WORDS, 1, 9);
        foreach (self::TENS as $word => $value) {
            if (preg_match_all('/(?<![\p{L}])' . $word . '(?:[\s\-]+(' . implode('|', $units) . '))?(?![\p{L}])/u', $lower, $m)) {
                foreach ($m[1] as $unit) {
                    $out[] = $unit === '' ? $value : $value + (int) array_search($unit, EntryReader::NUMBER_WORDS, true);
                }
            }
        }
        return array_values(array_unique($out));
    }

    /** Every number in the text, as digit strings and number-word values (as strings). @return list<string> */
    public static function all(string $text): array
    {
        return array_values(array_unique(array_merge(self::digits($text), array_map('strval', self::words($text)))));
    }

    /** True when $number (a canonical digit string) is written in $text as digits or as its word. */
    public static function mentions(string $text, string $number): bool
    {
        return in_array($number, self::all($text), true);
    }

    /** As in the disclosure guard: a value cue before "one" makes it the number. */
    private static function oneAsDeterminer(string $text, int $start, int $end): bool
    {
        $before = substr($text, 0, $start);
        if (preg_match('/(\b(is|was|be|equals|equal to|comes to|get|gets|got|give|gives|make|makes|answer|value|times|plus|minus|and)|=)\s*$/', $before)) {
            return false;
        }
        if (preg_match('/\b(each|this|that|no|which|every|any)\s+$/', $before)) {
            return true;
        }
        return (bool) preg_match('/^\s+[a-z]/', substr($text, $end));
    }
}
