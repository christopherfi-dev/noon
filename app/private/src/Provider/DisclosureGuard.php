<?php
declare(strict_types=1);

namespace AlgebraEngine\Provider;

use AlgebraEngine\Math\ExpressionParser;
use AlgebraEngine\Math\MathInputError;
use AlgebraEngine\Math\Normalizer;
use AlgebraEngine\Math\Polynomial;
use AlgebraEngine\Math\Rational;
use AlgebraEngine\Math\ScalarParser;

/**
 * Answer-disclosure guard (addendum D1). HEURISTIC: it rejects a model
 * message that contains a protected answer as a standalone token. It cannot
 * detect every paraphrase (e.g. an answer spelled out in an unusual way), so
 * it is recorded as a heuristic in the capability matrix.
 *
 * Protected answers are given as answer_spec arrays (kind, expected) plus,
 * for classification, the lettered options.
 */
final class DisclosureGuard
{
    private const WORDS = [
        0 => 'zero', 1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five', 6 => 'six', 7 => 'seven', 8 => 'eight', 9 => 'nine',
        10 => 'ten', 11 => 'eleven', 12 => 'twelve', 13 => 'thirteen', 14 => 'fourteen', 15 => 'fifteen', 16 => 'sixteen',
        17 => 'seventeen', 18 => 'eighteen', 19 => 'nineteen', 20 => 'twenty',
    ];

    /**
     * @param list<array{answer:array,variables?:list<string>,options?:?array}> $protected
     * @return list<string> reasons; empty when no disclosure was detected
     */
    public static function violations(string $message, array $protected): array
    {
        $text = self::normalize($message);
        $reasons = [];
        foreach ($protected as $p) {
            $a = $p['answer'];
            $hit = match ($a['kind']) {
                'exact_rational' => self::scalarHit($text, (string) $a['expected']),
                'polynomial' => self::expressionHit($text, (string) $a['expected'], $p['variables'] ?? ['x']),
                'classification' => self::classificationHit($text, (array) $a['expected'], $p['options'] ?? []),
                default => false,
            };
            if ($hit) {
                $reasons[] = 'student_message contains the expected answer of the active or a remaining guided step';
            }
        }
        return array_values(array_unique($reasons));
    }

    private static function normalize(string $message): string
    {
        $s = Normalizer::normalize($message);
        return strtolower($s);
    }

    private static function scalarHit(string $text, string $expected): bool
    {
        try {
            $value = ScalarParser::parse($expected);
        } catch (MathInputError) {
            return false;
        }
        // Standalone signed numbers and simple fractions in the message.
        if (preg_match_all('/(?<![\w.\/])(-?\s?\d+(?:\.\d+)?(?:\s?\/\s?\d+)?)(?![\w.\/]|\d)/', $text, $m)) {
            foreach ($m[1] as $token) {
                try {
                    if (ScalarParser::parse(str_replace(' ', '', $token))->equals($value)) {
                        return true;
                    }
                } catch (MathInputError) {
                }
            }
        }
        if ($value->isInteger()) {
            $n = $value->num();
            $word = self::WORDS[abs($n)] ?? null;
            if ($word !== null) {
                $pattern = $n < 0 ? '/\b(minus|negative)\s+' . $word . '\b/' : '/(?<!minus |negative )\b' . $word . '\b/';
                if (preg_match($pattern, $text)) {
                    return true;
                }
            }
            if ($n < 0 && preg_match('/\b(minus|negative)\s+' . abs($n) . '(?![\d.\/])/', $text)) {
                return true;
            }
        }
        return false;
    }

    /** Any maximal run of math characters that parses to the expected polynomial. */
    private static function expressionHit(string $text, string $expected, array $vars): bool
    {
        try {
            $target = Polynomial::fromNode(ExpressionParser::parse($expected, $vars));
        } catch (MathInputError) {
            return false;
        }
        $varClass = preg_quote(implode('', $vars), '/');
        if (!preg_match_all('/[0-9' . $varClass . '+\-*\/^(). ]+/', $text, $m)) {
            return false;
        }
        foreach ($m[0] as $span) {
            $span = trim($span, " .");
            if ($span === '' || !preg_match('/[' . $varClass . ']/', $span)) {
                continue;
            }
            foreach (self::subspans($span) as $candidate) {
                try {
                    if (Polynomial::fromNode(ExpressionParser::parse($candidate, $vars))->equals($target)) {
                        return true;
                    }
                } catch (MathInputError) {
                }
            }
        }
        return false;
    }

    /** The span itself plus its pieces split at spaces that separate words from math. @return list<string> */
    private static function subspans(string $span): array
    {
        $out = [$span];
        $parts = preg_split('/\s{2,}/', $span) ?: [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p !== '' && $p !== $span) {
                $out[] = $p;
            }
        }
        return $out;
    }

    private static function classificationHit(string $text, array $letters, array $options): bool
    {
        foreach ($letters as $letter) {
            $l = strtolower((string) $letter);
            if (preg_match('/\b(option|choice|answer|letter)\s*(is\s*)?\(?' . $l . '\)?(?![a-z])/', $text) || preg_match('/(?<![a-z])' . $l . '\)/', $text)) {
                return true;
            }
        }
        foreach ($options as $o) {
            if (in_array($o['letter'], $letters, true) && mb_strlen($o['text']) >= 4 && str_contains($text, strtolower(Normalizer::normalize($o['text'])))) {
                return true;
            }
        }
        return false;
    }
}
