<?php
declare(strict_types=1);

namespace AlgebraEngine\Tutor;

use AlgebraEngine\Math\Normalizer;

/**
 * Numbers written in a piece of text, as digits or as number words (zero to
 * 999, round 7 §7.2). Used by the reading check, the problem checks and the
 * before-check-card audit.
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
     * Values written as number words, including compounds up to 999 (round 7
     * §7.2): "twenty five", "twenty-five", "one hundred and four", "a hundred".
     * "One" used as a determiner or pronoun ("one way", "each one") is not the
     * value 1.
     *
     * @return list<int>
     */
    public static function words(string $text): array
    {
        $lower = mb_strtolower($text, 'UTF-8');
        $out = [];
        foreach (self::phrases($lower) as $p) {
            if ($p['text'] === 'one' && self::oneAsDeterminer($lower, $p['start'], $p['end'])) {
                continue;
            }
            $out[] = $p['value'];
        }
        return array_values(array_unique($out));
    }

    /** Kept for callers of the round 6 name; words() now reads compounds itself. @return list<int> */
    public static function wordsExtended(string $text): array
    {
        return self::words($text);
    }

    private const UNITS = ['zero' => 0, 'one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6, 'seven' => 7, 'eight' => 8, 'nine' => 9,
        'ten' => 10, 'eleven' => 11, 'twelve' => 12, 'thirteen' => 13, 'fourteen' => 14, 'fifteen' => 15, 'sixteen' => 16, 'seventeen' => 17,
        'eighteen' => 18, 'nineteen' => 19];
    private const TENS = ['twenty' => 20, 'thirty' => 30, 'forty' => 40, 'fifty' => 50, 'sixty' => 60, 'seventy' => 70, 'eighty' => 80, 'ninety' => 90];

    /**
     * Number-word phrases in lower-case text, longest first at each position:
     * [unit] hundred [and] [tens[-unit] | unit], tens[-unit], or a unit.
     * Words in a phrase are separated by spaces or one hyphen only.
     *
     * @return list<array{value:int,start:int,end:int,text:string}> byte offsets
     */
    public static function phrases(string $lower): array
    {
        preg_match_all('/\p{L}+/u', $lower, $m, PREG_OFFSET_CAPTURE);
        $w = array_map(fn ($x) => ['t' => $x[0], 's' => $x[1], 'e' => $x[1] + strlen($x[0])], $m[0]);
        $joined = function (int $a, int $b) use ($w, $lower): bool {
            if (!isset($w[$a], $w[$b])) {
                return false;
            }
            $gap = substr($lower, $w[$a]['e'], $w[$b]['s'] - $w[$a]['e']);
            return preg_match('/^(?:\s+|-)$/', $gap) === 1;
        };
        $out = [];
        $n = count($w);
        for ($i = 0; $i < $n;) {
            $t = $w[$i]['t'];
            $value = null;
            $j = $i;
            $lead = self::UNITS[$t] ?? ($t === 'a' ? 1 : null);
            if ($lead !== null && $lead > 0 && $lead < 10 && ($w[$i + 1]['t'] ?? '') === 'hundred' && $joined($i, $i + 1)) {
                $value = $lead * 100;
                $j = $i + 2;
                $k = $j;
                if (($w[$k]['t'] ?? '') === 'and' && $joined($k - 1, $k)) {
                    $k++;
                }
                if ($k < $n && $joined($k - 1, $k)) {
                    [$v, $next] = self::belowHundred($w, $k, $joined);
                    if ($v !== null && $v > 0) {
                        $value += $v;
                        $j = $next;
                    }
                }
            } elseif ($t !== 'a') {
                [$value, $j] = self::belowHundred($w, $i, $joined);
            }
            if ($value === null) {
                $i++;
                continue;
            }
            $out[] = ['value' => $value, 'start' => $w[$i]['s'], 'end' => $w[$j - 1]['e'], 'text' => substr($lower, $w[$i]['s'], $w[$j - 1]['e'] - $w[$i]['s'])];
            $i = $j;
        }
        return $out;
    }

    /** The whole text is one number-word phrase, optionally after "negative": its value, else null. */
    public static function phraseValue(string $text): ?int
    {
        $lower = trim((string) preg_replace('/\s+/u', ' ', mb_strtolower(trim($text), 'UTF-8')));
        $lower = rtrim($lower, '.!');
        $sign = 1;
        if (str_starts_with($lower, 'negative ')) {
            $sign = -1;
            $lower = substr($lower, 9);
        }
        $p = self::phrases($lower);
        return count($p) === 1 && $p[0]['start'] === 0 && $p[0]['end'] === strlen($lower) ? $sign * $p[0]['value'] : null;
    }

    /** @return array{0:?int,1:int} a value below 100 starting at word $i, and the index after it */
    private static function belowHundred(array $w, int $i, callable $joined): array
    {
        $t = $w[$i]['t'] ?? '';
        if (isset(self::TENS[$t])) {
            $u = $w[$i + 1]['t'] ?? '';
            if (isset(self::UNITS[$u]) && self::UNITS[$u] > 0 && self::UNITS[$u] < 10 && $joined($i, $i + 1)) {
                return [self::TENS[$t] + self::UNITS[$u], $i + 2];
            }
            return [self::TENS[$t], $i + 1];
        }
        if (isset(self::UNITS[$t])) {
            return [self::UNITS[$t], $i + 1];
        }
        return [null, $i];
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
        if (preg_match('/\b(each|this|that|no|which|every|any|another|the|a|next|last|first|other|new|harder|easier|different|same|right|wrong)\s+$/', $before)) {
            return true;
        }
        return (bool) preg_match('/^\s+[a-z]/', substr($text, $end));
    }
}
