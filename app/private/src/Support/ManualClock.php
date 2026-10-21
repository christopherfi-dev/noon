<?php
declare(strict_types=1);

namespace AlgebraEngine\Support;

/** Test clock; time only moves when advance() is called. */
final class ManualClock implements Clock
{
    private \DateTimeImmutable $now;

    public function __construct(string $start = '2026-10-21T12:00:00Z')
    {
        $this->now = new \DateTimeImmutable($start, new \DateTimeZone('UTC'));
    }

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(int $seconds): void
    {
        $this->now = $this->now->modify(($seconds >= 0 ? '+' : '') . $seconds . ' seconds');
    }
}
