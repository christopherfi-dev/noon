<?php
declare(strict_types=1);

namespace AlgebraEngine\Support;

final class Time
{
    public static function iso(\DateTimeImmutable $t): string
    {
        return $t->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }

    public static function parse(string $iso): \DateTimeImmutable
    {
        return new \DateTimeImmutable($iso, new \DateTimeZone('UTC'));
    }
}
