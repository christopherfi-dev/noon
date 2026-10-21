<?php
declare(strict_types=1);

namespace AlgebraEngine\Tests\Unit\Math;

use AlgebraEngine\Math\ExpressionParser;
use AlgebraEngine\Math\MathInputError;
use AlgebraEngine\Math\Polynomial;
use AlgebraEngine\Math\Rational;
use PHPUnit\Framework\TestCase;

/**
 * Equivalence tests. Expected polynomials are built directly from integers through the
 * Polynomial constructors (or hand-formatted canonical strings), not by parsing the same text.
 */
final class PolynomialTest extends TestCase
{
    private static function parse(string $s, array $vars = ['x']): Polynomial
    {
        return Polynomial::fromNode(ExpressionParser::parse($s, $vars));
    }

    private static function c(int $n): Polynomial
    {
        return Polynomial::constant(Rational::fromInt($n));
    }

    private static function x(): Polynomial
    {
        return Polynomial::variable('x');
    }

    /** Hand-formatted canonical form of p*x + q (p != 0). */
    private static function linear(int $p, int $q): string
    {
        $lead = match ($p) {
            1 => 'x',
            -1 => '-x',
            default => $p . '*x',
        };
        if ($q === 0) {
            return $lead;
        }
        return $lead . ($q > 0 ? '+' : '-') . abs($q);
    }

    /** @return list<array{int,int}> */
    private static function seededPairs(int $seed, int $count): array
    {
        mt_srand($seed);
        $pairs = [];
        while (count($pairs) < $count) {
            $a = mt_rand(-12, 12);
            $b = mt_rand(-12, 12);
            if ($a !== 0 && $b !== 0) {
                $pairs[] = [$a, $b];
            }
        }
        return $pairs;
    }

    public function testDistributivityMetamorphic(): void
    {
        foreach (self::seededPairs(20261021, 60) as [$a, $b]) {
            $parsed = self::parse("$a(x+$b)");
            $direct = self::c($a)->mul(self::x())->add(self::c($a * $b));
            self::assertTrue($parsed->equals($direct), "$a(x+$b)");
            self::assertSame(self::linear($a, $a * $b), $parsed->toString(), "$a(x+$b)");
            // Expanded form written by hand is equivalent; a sign flip on the constant is not.
            $expandedText = "{$a}x" . ($a * $b >= 0 ? '+' : '') . ($a * $b);
            self::assertTrue(self::parse($expandedText)->equals($parsed), $expandedText);
            $flipped = "{$a}x" . (-$a * $b >= 0 ? '+' : '') . (-$a * $b);
            self::assertFalse(self::parse($flipped)->equals($parsed), $flipped);
            // Distribution to the first term only is not equivalent.
            $firstOnly = "{$a}x" . ($b >= 0 ? '+' : '') . $b;
            if ($a !== 1) {
                self::assertFalse(self::parse($firstOnly)->equals($parsed), $firstOnly);
            }
        }
    }

    public function testCommutativityAndAssociativity(): void
    {
        foreach (self::seededPairs(7, 40) as [$a, $b]) {
            $p = self::parse("{$a}x+($b)");
            self::assertTrue($p->equals(self::parse("($b)+x*($a)")));
            self::assertTrue(self::parse("($a)*(($b)*x)")->equals(self::parse("(($a)*($b))*x")));
            self::assertTrue(self::parse("(x+$a)(x+$b)")->equals(self::parse("(x+$b)(x+$a)")));
        }
    }

    public function testSquareAndDifferenceOfSquaresIdentities(): void
    {
        for ($a = -9; $a <= 9; $a++) {
            $square = self::x()->mul(self::x())->add(self::c(2 * $a)->mul(self::x()))->add(self::c($a * $a));
            self::assertTrue(self::parse("(x+($a))^2")->equals($square), "(x+$a)^2");
            $diff = self::x()->mul(self::x())->sub(self::c($a * $a));
            self::assertTrue(self::parse("(x-($a))(x+($a))")->equals($diff), "(x-$a)(x+$a)");
            if ($a !== 0) {
                // (x+a)^2 is not x^2 + a^2.
                self::assertFalse(self::parse("(x+($a))^2")->equals(self::x()->mul(self::x())->add(self::c($a * $a))));
            }
        }
    }

    public function testNoSamplingCoincidence(): void
    {
        // x(x-1)(x-2) vanishes at x=0,1,2 but is not the zero polynomial.
        $p = self::parse('x(x-1)(x-2)');
        self::assertFalse($p->isZero());
        self::assertSame('x^3-3*x^2+2*x', $p->toString());
        self::assertTrue(self::parse('x*x')->equals(self::parse('x^2')));
    }

    public function testRationalCoefficientsAndConstants(): void
    {
        self::assertSame('1/2*x+3/4', self::parse('(2x+3)/4')->toString());
        self::assertSame('5', self::parse('2+3')->constantValue()?->toString());
        self::assertNull(self::parse('x+1')->constantValue());
        self::assertSame('0', self::parse('x-x')->toString());
        self::assertTrue(self::parse('x-x')->isZero());
        self::assertSame(['x', 'y'], self::parse('y+x*y', ['x', 'y'])->variables());
        self::assertSame(2, self::parse('x*y+1', ['x', 'y'])->degree());
        self::assertSame(1, self::parse('x*y+1', ['x', 'y'])->degreeIn('y'));
        self::assertSame('3', self::parse('3x+2')->coefficientOf('x')->toString());
    }

    public function testDegreeAndSizeLimits(): void
    {
        foreach (['(x+1)^10*(x+1)', 'x^10*x'] as $s) {
            try {
                self::parse($s);
                self::fail('expected TOO_LARGE for ' . $s);
            } catch (MathInputError $e) {
                self::assertSame('TOO_LARGE', $e->errorCode);
            }
        }
        self::assertSame(10, self::parse('(x+1)^10')->degree());
    }
}
