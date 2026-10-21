<?php
declare(strict_types=1);

namespace AlgebraEngine\Items;

use AlgebraEngine\Math\Rational;

/** Learner-facing number formatting. Negative numbers use the Unicode minus sign. */
final class Fmt
{
    public const MINUS = "\u{2212}";

    public static function n(int|Rational $v): string
    {
        $s = $v instanceof Rational ? $v->toString() : (string) $v;
        return str_starts_with($s, '-') ? self::MINUS . substr($s, 1) : $s;
    }

    /** Parenthesise negatives, e.g. "(−6)". */
    public static function p(int|Rational $v): string
    {
        $s = self::n($v);
        return str_starts_with($s, self::MINUS) ? '(' . $s . ')' : $s;
    }

    /** "x + 4" / "x − 2" for a constant added to a term. */
    public static function plus(string $term, int $k): string
    {
        return $k < 0 ? $term . ' ' . self::MINUS . ' ' . (-$k) : $term . ' + ' . $k;
    }

    /** Coefficient times x: "3x", "−3x", "x", "−x". */
    public static function cx(int $a, string $var = 'x'): string
    {
        return match ($a) {
            1 => $var,
            -1 => self::MINUS . $var,
            default => self::n($a) . $var,
        };
    }

    public static function abs(int $v): int
    {
        return $v < 0 ? -$v : $v;
    }
}
