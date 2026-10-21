<?php
declare(strict_types=1);

namespace AlgebraEngine\Math;

/**
 * Standardises typographic variants of signs and spaces. It never removes a minus sign,
 * changes an exponent or turns an equation into something else; unknown non-ASCII
 * characters are left in place for the parsers to reject.
 */
final class Normalizer
{
    private const MAP = [
        "\u{2212}" => '-', // minus sign
        "\u{2012}" => '-', // figure dash
        "\u{2013}" => '-', // en dash
        "\u{FE63}" => '-', // small hyphen-minus
        "\u{FF0D}" => '-', // full-width hyphen-minus
        "\u{00D7}" => '*', // multiplication sign
        "\u{00B7}" => '*', // middle dot
        "\u{2219}" => '*', // bullet operator
        "\u{22C5}" => '*', // dot operator
        "\u{00F7}" => '/', // division sign
        "\u{00A0}" => ' ',
        "\u{2007}" => ' ',
        "\u{2009}" => ' ',
        "\u{200A}" => ' ',
        "\u{202F}" => ' ',
        "\u{3000}" => ' ',
        "\t" => ' ',
        "\r" => ' ',
        "\n" => ' ',
    ];

    public static function normalize(string $text): string
    {
        $text = strtr($text, self::MAP);
        $text = preg_replace('/ {2,}/', ' ', $text) ?? $text;
        return trim($text);
    }
}
