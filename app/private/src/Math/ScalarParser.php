<?php
declare(strict_types=1);

namespace AlgebraEngine\Math;

/**
 * Reads a single exact number: integer, terminating decimal or simple fraction.
 * It does not evaluate arithmetic; "27/3-4" is not a single number.
 */
final class ScalarParser
{
    public const MAX_LENGTH = 200;
    public const MAX_DIGITS = 12;

    public static function parse(string $text, ?string $variable = null): Rational
    {
        if (strlen($text) > self::MAX_LENGTH) {
            throw new MathInputError('TOO_LONG');
        }
        $s = Normalizer::normalize($text);
        if ($s === '') {
            throw new MathInputError('EMPTY');
        }
        if ($variable !== null) {
            $v = preg_quote($variable, '/');
            // "x = 3", and the reversed "3 = x" (round 7 §7.3).
            if (preg_match('/^' . $v . ' ?= ?(.+)$/', $s, $m) === 1 || preg_match('/^(.+?) ?= ?' . $v . '$/', $s, $m) === 1) {
                $s = trim($m[1]);
                if ($s === '') {
                    throw new MathInputError('NOT_A_SINGLE_NUMBER');
                }
            }
        }
        if (preg_match('/[^0-9a-zA-Z.+\-\/() =*^]/', $s) === 1) {
            throw new MathInputError('UNSUPPORTED_CHARACTER');
        }
        if (preg_match('/[a-zA-Z]{2,}/', $s) === 1) {
            throw new MathInputError('UNSUPPORTED_WORD');
        }
        // "1 1/2", "-2 3/4": whole number followed by a fraction.
        if (preg_match('/^[+-]?\d+ \d+ ?\/ ?\d+$/', $s) === 1) {
            throw new MathInputError('AMBIGUOUS_MIXED_NUMBER');
        }
        // "(-3)" style wrapper around a signed number.
        if (preg_match('/^\( ?([+-]? ?[0-9.]+) ?\)$/', $s, $m) === 1) {
            $s = $m[1];
        }
        // Digits separated only by spaces are two numbers, not one.
        if (preg_match('/[\d.] +[\d.]/', $s) === 1) {
            throw new MathInputError('NOT_A_SINGLE_NUMBER');
        }
        $s = str_replace(' ', '', $s);

        if (preg_match('/^([+-]?)(\d+)\/([+-]?)(\d+)$/', $s, $m) === 1) {
            $num = self::toInt($m[2]);
            $den = self::toInt($m[4]);
            if ($den === 0) {
                throw new MathInputError('ZERO_DENOMINATOR');
            }
            $negative = ($m[1] === '-') !== ($m[3] === '-');
            return Rational::of($negative ? -$num : $num, $den);
        }
        if (preg_match('/^([+-]?)(\d*)\.(\d+)$/', $s, $m) === 1 || preg_match('/^([+-]?)(\d+)\.()$/', $s, $m) === 1) {
            $whole = $m[2] === '' ? '0' : $m[2];
            $frac = $m[3];
            if (strlen(ltrim($whole, '0')) > self::MAX_DIGITS || strlen($frac) > self::MAX_DIGITS) {
                throw new MathInputError('TOO_MANY_DIGITS');
            }
            $den = 10 ** strlen($frac);
            $value = Rational::fromInt(self::toInt($whole))->add(Rational::of($frac === '' ? 0 : self::toInt($frac), $den));
            return $m[1] === '-' ? $value->neg() : $value;
        }
        if (preg_match('/^([+-]?)(\d+)$/', $s, $m) === 1) {
            $n = self::toInt($m[2]);
            return Rational::fromInt($m[1] === '-' ? -$n : $n);
        }
        throw new MathInputError('NOT_A_SINGLE_NUMBER');
    }

    private static function toInt(string $digits): int
    {
        $trimmed = ltrim($digits, '0');
        if (strlen($trimmed) > self::MAX_DIGITS) {
            throw new MathInputError('TOO_MANY_DIGITS');
        }
        return $trimmed === '' ? 0 : (int) $trimmed;
    }
}
