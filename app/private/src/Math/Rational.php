<?php
declare(strict_types=1);

namespace AlgebraEngine\Math;

/**
 * Exact rational number on native integers. Every operation is overflow-checked;
 * values outside +/-10^15 (numerator or denominator) raise TOO_LARGE. No floats are used.
 */
final class Rational
{
    public const BOUND = 1_000_000_000_000_000;

    private function __construct(private readonly int $n, private readonly int $d)
    {
    }

    public static function of(int $num, int $den = 1): self
    {
        if ($den === 0) {
            throw new MathInputError('ZERO_DENOMINATOR');
        }
        if ($num === PHP_INT_MIN || $den === PHP_INT_MIN) {
            throw new MathInputError('TOO_LARGE');
        }
        if ($den < 0) {
            $num = -$num;
            $den = -$den;
        }
        $g = self::gcd(abs($num), $den);
        if ($g > 1) {
            $num = intdiv($num, $g);
            $den = intdiv($den, $g);
        }
        if ($num > self::BOUND || $num < -self::BOUND || $den > self::BOUND) {
            throw new MathInputError('TOO_LARGE');
        }
        return new self($num, $den);
    }

    public static function fromInt(int $n): self
    {
        return self::of($n, 1);
    }

    public static function zero(): self
    {
        return new self(0, 1);
    }

    public function num(): int
    {
        return $this->n;
    }

    public function den(): int
    {
        return $this->d;
    }

    public function add(self $o): self
    {
        $g = self::gcd($this->d, $o->d);
        $lcm = self::mul2(intdiv($this->d, $g), $o->d);
        $a = self::mul2($this->n, intdiv($lcm, $this->d));
        $b = self::mul2($o->n, intdiv($lcm, $o->d));
        return self::of(self::add2($a, $b), $lcm);
    }

    public function sub(self $o): self
    {
        return $this->add($o->neg());
    }

    public function mul(self $o): self
    {
        if ($this->n === 0 || $o->n === 0) {
            return self::zero();
        }
        $g1 = self::gcd(abs($this->n), $o->d);
        $g2 = self::gcd(abs($o->n), $this->d);
        $num = self::mul2(intdiv($this->n, $g1), intdiv($o->n, $g2));
        $den = self::mul2(intdiv($this->d, $g2), intdiv($o->d, $g1));
        return self::of($num, $den);
    }

    public function div(self $o): self
    {
        if ($o->n === 0) {
            throw new MathInputError('ZERO_DENOMINATOR');
        }
        return $this->mul(self::of($o->d, $o->n));
    }

    public function neg(): self
    {
        return new self(-$this->n, $this->d);
    }

    public function abs(): self
    {
        return new self(abs($this->n), $this->d);
    }

    public function equals(self $o): bool
    {
        return $this->n === $o->n && $this->d === $o->d;
    }

    public function compare(self $o): int
    {
        return $this->sub($o)->sign();
    }

    public function isZero(): bool
    {
        return $this->n === 0;
    }

    public function isInteger(): bool
    {
        return $this->d === 1;
    }

    public function sign(): int
    {
        return $this->n <=> 0;
    }

    public function toString(): string
    {
        return $this->d === 1 ? (string) $this->n : $this->n . '/' . $this->d;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    private static function gcd(int $a, int $b): int
    {
        while ($b !== 0) {
            [$a, $b] = [$b, $a % $b];
        }
        return $a === 0 ? 1 : $a;
    }

    private static function mul2(int $a, int $b): int
    {
        $r = $a * $b;
        if (!is_int($r)) {
            throw new MathInputError('TOO_LARGE');
        }
        return $r;
    }

    private static function add2(int $a, int $b): int
    {
        $r = $a + $b;
        if (!is_int($r)) {
            throw new MathInputError('TOO_LARGE');
        }
        return $r;
    }
}
