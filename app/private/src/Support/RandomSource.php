<?php
declare(strict_types=1);

namespace AlgebraEngine\Support;

/**
 * Integer randomness for item parameters. Production uses random_int; tests
 * and the mock provider use a seeded generator so runs are reproducible.
 */
final class RandomSource
{
    private int $counter = 0;

    private function __construct(private ?string $seed)
    {
    }

    public static function secure(): self
    {
        return new self(null);
    }

    public static function seeded(string $seed): self
    {
        return new self($seed);
    }

    public function int(int $min, int $max): int
    {
        if ($min > $max) {
            throw new \InvalidArgumentException('min > max');
        }
        if ($this->seed === null) {
            return random_int($min, $max);
        }
        // Hash-counter stream: deterministic for a seed, no integer overflow.
        $value = hexdec(substr(hash('sha256', $this->seed . ':' . $this->counter++), 0, 12));
        return $min + ($value % ($max - $min + 1));
    }

    /** @param list<mixed> $items */
    public function pick(array $items): mixed
    {
        return $items[$this->int(0, count($items) - 1)];
    }

    /** @param list<mixed> $items @return list<mixed> */
    public function shuffle(array $items): array
    {
        for ($i = count($items) - 1; $i > 0; $i--) {
            $j = $this->int(0, $i);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }
        return $items;
    }
}
