<?php
declare(strict_types=1);

namespace AlgebraEngine\Tests\Unit\Math;

use AlgebraEngine\Math\EquationParser;
use AlgebraEngine\Math\LinearEquation;
use AlgebraEngine\Math\MathInputError;
use PHPUnit\Framework\TestCase;

final class LinearEquationTest extends TestCase
{
    private static function solve(string $eq, array $vars = ['x']): \AlgebraEngine\Math\LinearSolution
    {
        [$l, $r] = EquationParser::parse($eq, $vars);
        return LinearEquation::solve($l, $r);
    }

    public function testUniqueSolutions(): void
    {
        self::assertSame('5', self::solve('3(x+4)=27')->value?->toString());
        self::assertSame('23/3', self::solve('3x+4=27')->value?->toString());
        self::assertSame('UNIQUE', self::solve('3x+4=27')->kind);
        self::assertSame('5', self::solve('x+4=9')->value?->toString());
        self::assertSame('7', self::solve('15=3(x-2)')->value?->toString());
        self::assertSame('-2', self::solve('2(x+5)+1=7')->value?->toString());
    }

    public function testDegenerateCases(): void
    {
        self::assertSame('ALL', self::solve('2x=2x')->kind);
        self::assertNull(self::solve('2x=2x')->value);
        self::assertSame('NONE', self::solve('2x=2x+1')->kind);
    }

    public function testNotLinear(): void
    {
        foreach (['x^2=4', 'x*y=1'] as $eq) {
            try {
                self::solve($eq, ['x', 'y']);
                self::fail('expected NOT_LINEAR for ' . $eq);
            } catch (MathInputError $e) {
                self::assertSame('NOT_LINEAR', $e->errorCode);
            }
        }
    }
}
