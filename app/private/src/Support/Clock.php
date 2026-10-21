<?php
declare(strict_types=1);

namespace AlgebraEngine\Support;

interface Clock
{
    public function now(): \DateTimeImmutable;
}
