<?php
declare(strict_types=1);

namespace AlgebraEngine\Support;

final class Ids
{
    public static function make(string $prefix): string
    {
        return $prefix . '_' . bin2hex(random_bytes(12));
    }

    public static function secret(): string
    {
        return bin2hex(random_bytes(32));
    }
}
