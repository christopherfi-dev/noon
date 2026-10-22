<?php
declare(strict_types=1);

namespace AlgebraEngine\Provider;

use AlgebraEngine\Math\ExpressionParser;
use AlgebraEngine\Math\FormInspector;
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
 *
 * Round 4 ruling 1 narrows it in exactly two cases; everything else is
 * still checked:
 * (a) answer options named together as alternatives ("positive or
 *     negative") are not read as choosing one of them;
 * (b) a value that is the learner's own answer to an already-completed step
 *     is not protected, except as the answer of the step being asked now.
 */
final class DisclosureGuard
{
    private const WORDS = [
        0 => 'zero', 1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five', 6 => 'six', 7 => 'seven', 8 => 'eight', 9 => 'nine',
        10 => 'ten', 11 => 'eleven', 12 => 'twelve', 13 => 'thirteen', 14 => 'fourteen', 15 => 'fifteen', 16 => 'sixteen',
        17 => 'seventeen', 18 => 'eighteen', 19 => 'nineteen', 20 => 'twenty',
    ];

    /**
     * @param list<array{answer:array,variables?:list<string>,options?:?array,active?:bool}> $protected
     * @param list<array> $completed answer specs of completed steps (the learner's own correct answers)
     * @return list<string> reasons; empty when no disclosure was detected
     */
    public static function violations(string $message, array $protected, array $completed = []): array
    {
        $text = self::normalize($message);
        $reasons = [];
        foreach ($protected as $p) {
            $a = $p['answer'];
            // (b): the learner already gave this value; only the active step's answer stays protected.
            if (!($p['active'] ?? true) && self::alreadyAnswered($a, $completed, $p['variables'] ?? ['x'])) {
                continue;
            }
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
        foreach (self::numberTokens($text) as $token) {
            // For a whole-number answer, "80 ÷ 10" is a division to do, not the answer written down.
            if ($value->isInteger() && str_contains($token, '/')) {
                continue;
            }
            try {
                if (ScalarParser::parse($token)->equals($value)) {
                    return true;
                }
            } catch (MathInputError) {
            }
        }
        if ($value->isInteger()) {
            $n = $value->num();
            $word = self::WORDS[abs($n)] ?? null;
            if ($word !== null) {
                $pattern = $n < 0 ? '/\b(minus|negative)\s+' . $word . '\b/' : '/(?<!minus |negative )\b' . $word . '\b/';
                if (preg_match_all($pattern, $text, $m, PREG_OFFSET_CAPTURE)) {
                    foreach ($m[0] as [$found, $offset]) {
                        if ($n !== 1 || !self::oneAsDeterminer($text, $offset, $offset + strlen($found))) {
                            return true;
                        }
                    }
                }
            }
            if ($n < 0 && preg_match('/\b(minus|negative)\s+' . abs($n) . '(?![\d\/]|\.\d)/', $text)) {
                return true;
            }
        }
        return false;
    }

    /**
     * "One" is also an English determiner and pronoun ("one way to check",
     * "on one side", "each one"). Those uses are not the value 1. A value cue
     * before it ("x is one", "equals one", "= one") always counts as the value.
     */
    private static function oneAsDeterminer(string $text, int $start, int $end): bool
    {
        $before = substr($text, 0, $start);
        if (preg_match('/(\b(is|was|be|equals|equal to|comes to|get|gets|got|give|gives|make|makes|answer|value)|=)\s*$/', $before)) {
            return false;
        }
        if (preg_match('/\b(each|this|that|no|which|every|any)\s+$/', $before)) {
            return true;
        }
        return (bool) preg_match('/^\s+[a-z]/', substr($text, $end));
    }

    /**
     * Numbers written in the text. A minus sign counts as a negative sign
     * only when it is not acting as subtraction: "x − 4" and "x−4" give 4,
     * while "is −8" and "(−6)" give −8 and −6.
     *
     * @return list<string>
     */
    private static function numberTokens(string $text): array
    {
        $out = [];
        if (!preg_match_all('/(-\s?)?\d+(?:\.\d+)?(?:\s?\/\s?\d+)?/', $text, $m, PREG_OFFSET_CAPTURE)) {
            return [];
        }
        foreach ($m[0] as [$token, $offset]) {
            $before = $offset > 0 ? $text[$offset - 1] : '';
            if ($before !== '' && (ctype_alnum($before) || $before === '.' || $before === '/')) {
                continue; // part of a longer word or number
            }
            $after = $text[$offset + strlen($token)] ?? '';
            if ($after !== '' && (ctype_alnum($after) || $after === '/' || ($after === '.' && ctype_digit($text[$offset + strlen($token) + 1] ?? '')))) {
                continue;
            }
            $token = str_replace(' ', '', $token);
            if (str_starts_with($token, '-')) {
                $prev = rtrim(substr($text, 0, $offset));
                $last = $prev === '' ? '' : $prev[strlen($prev) - 1];
                $spacedOperator = ($text[$offset + 1] ?? '') === ' ';
                $attached = $before !== '' && $before !== ' ';
                if (($attached && (ctype_alnum($before) || $before === ')')) || ($spacedOperator && ($last !== '' && (ctype_alnum($last) || $last === ')')))) {
                    $token = substr($token, 1);
                }
            }
            $out[] = $token;
        }
        return $out;
    }

    /** Any maximal run of math characters that is the expected polynomial written in expanded form. */
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
                    $node = ExpressionParser::parse($candidate, $vars);
                    // Only the answer as it would be written counts: the grouped
                    // problem expression is equal but is the question, not the answer.
                    if (FormInspector::expandedSimplified($node) === null && Polynomial::fromNode($node)->equals($target)) {
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

    /** True when a completed step's answer has the same value as this protected answer. */
    private static function alreadyAnswered(array $answer, array $completed, array $vars): bool
    {
        foreach ($completed as $c) {
            if (($c['kind'] ?? null) !== $answer['kind']) {
                continue;
            }
            try {
                $same = match ($answer['kind']) {
                    'exact_rational' => ScalarParser::parse((string) $c['expected'])->equals(ScalarParser::parse((string) $answer['expected'])),
                    'polynomial' => Polynomial::fromNode(ExpressionParser::parse((string) $c['expected'], $vars))
                        ->equals(Polynomial::fromNode(ExpressionParser::parse((string) $answer['expected'], $vars))),
                    'classification' => (array) $c['expected'] == (array) $answer['expected'],
                    default => false,
                };
            } catch (MathInputError) {
                $same = false;
            }
            if ($same) {
                return true;
            }
        }
        return false;
    }

    /**
     * (a): remove spans that list two or more different options as alternatives
     * ("positive or negative", "options A or B"), so naming the choices is not
     * read as choosing one.
     */
    private static function withoutAlternatives(string $text, array $options): string
    {
        $texts = array_values(array_filter(array_map(fn ($o) => strtolower(Normalizer::normalize($o['text'])), $options), fn ($t) => mb_strlen($t) >= 4));
        if (count($texts) >= 2) {
            $alt = '(' . implode('|', array_map(fn ($t) => preg_quote($t, '/'), $texts)) . ')';
            $text = (string) preg_replace_callback('/' . $alt . '(?:\s*(?:,\s*or|,|\/|\bor\b)\s*' . $alt . ')+/u', function ($m) use ($texts) {
                $named = [];
                foreach ($texts as $t) {
                    if (str_contains($m[0], $t)) {
                        $named[] = $t;
                    }
                }
                return count($named) >= 2 ? ' ' : $m[0];
            }, $text);
        }
        return (string) preg_replace('/\b(options?|choices?|letters?)\s+\(?[a-d]\)?(?:\s*(?:,\s*or|,|\/|\bor\b)\s*\(?[a-d]\)?)+/', ' ', $text);
    }

    private static function classificationHit(string $text, array $letters, array $options): bool
    {
        $text = self::withoutAlternatives($text, $options);
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
