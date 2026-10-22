<?php
declare(strict_types=1);

namespace AlgebraEngine\Progress;

/**
 * Round UI v3 (U10): progress codes. Twelve characters from an alphabet
 * without 0, O, 1, I or L, shown in groups of four (K7QM-4XRT-9WPC). Only the
 * sha256 of a code is stored on the server.
 */
final class ProgressCode
{
    /** 31 symbols: 2–9 and A–Z without I, L and O. */
    public const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';
    public const LENGTH = 12;

    /** A new code from the system's secure random source (12 × log2(31) ≈ 59.4 bits). */
    public static function generate(): string
    {
        $code = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }
        return $code;
    }

    /** Typed input in any case, with or without spaces or dashes; null when it cannot be a code. */
    public static function normalize(string $input): ?string
    {
        $code = strtoupper((string) preg_replace('/[\s\-‐‑–—]+/u', '', $input));
        if (strlen($code) !== self::LENGTH || strspn($code, self::ALPHABET) !== self::LENGTH) {
            return null;
        }
        return $code;
    }

    /** "K7QM4XRT9WPC" → "K7QM-4XRT-9WPC". */
    public static function format(string $code): string
    {
        return implode('-', str_split($code, 4));
    }

    public static function hash(string $code): string
    {
        return hash('sha256', $code);
    }
}
