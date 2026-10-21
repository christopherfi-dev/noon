<?php
declare(strict_types=1);

namespace AlgebraEngine\Support;

final class Json
{
    public static function encode(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
    }

    public static function pretty(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }

    /** Decode as associative arrays with a depth bound; throws \JsonException on failure. */
    public static function decode(string $text, int $depth = 32): mixed
    {
        return json_decode($text, true, $depth, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
    }

    /** Decode into stdClass objects (needed for JSON Schema validation). */
    public static function decodeObject(string $text, int $depth = 32): mixed
    {
        return json_decode($text, false, $depth, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
    }

    public static function toObject(mixed $value): mixed
    {
        return json_decode(self::encode($value), false, 64, JSON_THROW_ON_ERROR);
    }
}
