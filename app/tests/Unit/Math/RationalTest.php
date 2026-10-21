<?php
declare(strict_types=1);

namespace AlgebraEngine\Tests\Unit\Math;

use AlgebraEngine\Math\MathInputError;
use AlgebraEngine\Math\Rational;
use PHPUnit\Framework\TestCase;

final class RationalTest extends TestCase
{
    public function testReducesAndNormalisesSign(): void
    {
        $r = Rational::of(6, -8);
        self::assertSame(-3, $r->num());
        self::assertSame(4, $r->den());
        self::assertSame('-3/4', $r->toString());
        self::assertSame('0', Rational::of(0, -5)->toString());
    }

    public function testArithmetic(): void
    {
        $half = Rational::of(1, 2);
        $third = Rational::of(1, 3);
        self::assertSame('5/6', $half->add($third)->toString());
        self::assertSame('1/6', $half->sub($third)->toString());
        self::assertSame('1/6', $half->mul($third)->toString());
        self::assertSame('3/2', $half->div($third)->toString());
        self::assertSame('-1/2', $half->neg()->toString());
        self::assertSame('1/2', $half->neg()->abs()->toString());
        self::assertTrue(Rational::of(2, 4)->equals($half));
        self::assertSame(1, $half->compare($third));
        self::assertSame(-1, $third->compare($half));
        self::assertSame(0, $half->compare(Rational::of(3, 6)));
        self::assertTrue(Rational::fromInt(7)->isInteger());
        self::assertFalse($half->isInteger());
        self::assertSame(-1, Rational::of(-1, 9)->sign());
        self::assertTrue(Rational::of(0, 3)->isZero());
    }

    public function testZeroDenominatorAndDivisionByZero(): void
    {
        try {
            Rational::of(1, 0);
            self::fail('expected ZERO_DENOMINATOR');
        } catch (MathInputError $e) {
            self::assertSame('ZERO_DENOMINATOR', $e->errorCode);
        }
        $this->expectException(MathInputError::class);
        Rational::fromInt(3)->div(Rational::fromInt(0));
    }

    public function testOverflowIsCheckedNotSilentlyFloat(): void
    {
        $big = Rational::fromInt(999_999_999_999_999);
        foreach ([fn () => $big->mul($big), fn () => $big->add($big), fn () => Rational::of(PHP_INT_MIN, 1), fn () => Rational::of(1, PHP_INT_MAX)] as $op) {
            try {
                $op();
                self::fail('expected TOO_LARGE');
            } catch (MathInputError $e) {
                self::assertSame('TOO_LARGE', $e->errorCode);
            }
        }
        // Cross-cancellation keeps exact results inside the bound.
        $x = Rational::of(999_999_999_999_999, 7)->mul(Rational::of(7, 999_999_999_999_999));
        self::assertSame('1', $x->toString());
    }
}
