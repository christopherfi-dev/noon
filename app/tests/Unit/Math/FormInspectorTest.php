<?php
declare(strict_types=1);

namespace AlgebraEngine\Tests\Unit\Math;

use AlgebraEngine\Math\ExpressionParser;
use AlgebraEngine\Math\FormInspector;
use PHPUnit\Framework\TestCase;

final class FormInspectorTest extends TestCase
{
    public function testExpandedSimplified(): void
    {
        $cases = [
            '3x+6' => null,
            '6+3x' => null,
            '(3x+6)' => null,
            '3x' => null,
            '0' => null,
            '8x+20' => null,
            '-3x-6' => null,
            '3x/2+1' => null,
            'x^2-1' => null,
            '3(x+2)' => 'STILL_GROUPED',
            '(x+2)*3' => 'STILL_GROUPED',
            '-(x+2)' => 'STILL_GROUPED',
            '5-(x+2)' => 'STILL_GROUPED',
            '(x+2)^2' => 'STILL_GROUPED',
            '3x+3*2' => 'NUMBERS_NOT_MULTIPLIED',
            '2*3x' => 'NUMBERS_NOT_MULTIPLIED',
            '3x+2^3' => 'NUMBERS_NOT_MULTIPLIED',
            '2x+x+1' => 'LIKE_TERMS_NOT_COMBINED',
            '3x+4+2' => 'LIKE_TERMS_NOT_COMBINED',
            '3x+0' => 'ZERO_TERM',
        ];
        foreach ($cases as $expr => $reason) {
            self::assertSame($reason, FormInspector::expandedSimplified(ExpressionParser::parse((string) $expr)), (string) $expr);
        }
    }

    public function testFactored(): void
    {
        $cases = [
            '(x-1)(x+1)' => null,
            '3(x+2)' => null,
            '(x+1)^2' => null,
            '-(x-1)(x+1)' => null,
            '((x-1)(x+1))' => null,
            'x(x+1)' => null,
            'x^2-1' => 'NOT_A_PRODUCT',
            '3x+6' => 'NOT_A_PRODUCT',
            '2*3' => 'NOT_A_PRODUCT',
            'x*x' => 'NOT_A_PRODUCT',
        ];
        foreach ($cases as $expr => $reason) {
            self::assertSame($reason, FormInspector::factored(ExpressionParser::parse((string) $expr)), (string) $expr);
        }
    }
}
