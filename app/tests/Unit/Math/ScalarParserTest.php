<?php
declare(strict_types=1);

namespace AlgebraEngine\Tests\Unit\Math;

use AlgebraEngine\Math\MathInputError;
use AlgebraEngine\Math\Rational;
use AlgebraEngine\Math\ScalarParser;
use PHPUnit\Framework\TestCase;

final class ScalarParserTest extends TestCase
{
    /** @return array<string, array{0:string,1:string,2:?string}> */
    public static function accepted(): array
    {
        return [
            'integer' => ['7', '7', null],
            'negative integer' => ['-12', '-12', null],
            'unicode minus' => ["\u{2212}5", '-5', null],
            'decimal half' => ['0.5', '1/2', null],
            'leading-dot decimal' => ['.25', '1/4', null],
            'negative decimal' => ['-2.5', '-5/2', null],
            'trailing dot' => ['3.', '3', null],
            'fraction reduces' => ['-6/8', '-3/4', null],
            'sign on denominator' => ['3/-4', '-3/4', null],
            'double negative fraction' => ['-3/-4', '3/4', null],
            'parenthesised negative' => ['(-3)', '-3', null],
            'spaces around fraction bar' => [' 7 / 2 ', '7/2', null],
            'x equals' => ['x = 7', '7', 'x'],
            'x equals fraction' => ['x=-7/2', '-7/2', 'x'],
            'plus sign' => ['+4', '4', null],
        ];
    }

    /** @dataProvider accepted */
    public function testAccepted(string $input, string $expected, ?string $variable): void
    {
        self::assertSame($expected, ScalarParser::parse($input, $variable)->toString());
    }

    /** @return array<string, array{0:string,1:string,2:?string}> */
    public static function rejected(): array
    {
        return [
            'empty' => ['   ', 'EMPTY', null],
            'zero denominator' => ['1/0', 'ZERO_DENOMINATOR', null],
            'mixed number' => ['1 1/2', 'AMBIGUOUS_MIXED_NUMBER', null],
            'negative mixed number' => ['-2 3/4', 'AMBIGUOUS_MIXED_NUMBER', null],
            'arithmetic expression' => ['27/3-4', 'NOT_A_SINGLE_NUMBER', null],
            'two numbers' => ['3 4', 'NOT_A_SINGLE_NUMBER', null],
            'word' => ['seven', 'UNSUPPORTED_WORD', null],
            'code' => ["system('ls')", 'UNSUPPORTED_CHARACTER', null],
            'too many digits' => ['1234567890123', 'TOO_MANY_DIGITS', null],
            'too many decimals' => ['0.1234567890123', 'TOO_MANY_DIGITS', null],
            'too long' => [str_repeat('1', 201), 'TOO_LONG', null],
            'x equals without variable allowed' => ['x = 7', 'NOT_A_SINGLE_NUMBER', null],
            'other variable' => ['y = 7', 'NOT_A_SINGLE_NUMBER', 'x'],
            'x equals nothing' => ['x =', 'NOT_A_SINGLE_NUMBER', 'x'],
            'malformed interval' => ['[1,2', 'UNSUPPORTED_CHARACTER', null],
        ];
    }

    /** @dataProvider rejected */
    public function testRejectedFailsClosed(string $input, string $code, ?string $variable): void
    {
        try {
            ScalarParser::parse($input, $variable);
            self::fail('expected MathInputError ' . $code);
        } catch (MathInputError $e) {
            self::assertSame($code, $e->errorCode);
            self::assertNotSame('', $e->clarification());
        }
    }

    public function testDecimalAndFractionAreExactlyEqual(): void
    {
        self::assertTrue(ScalarParser::parse('0.5')->equals(Rational::of(1, 2)));
        self::assertTrue(ScalarParser::parse('-0.75')->equals(ScalarParser::parse('-3/4')));
    }
}
