<?php
declare(strict_types=1);

namespace AlgebraEngine\Tests\Unit\Math;

use AlgebraEngine\Math\Ast\BinOp;
use AlgebraEngine\Math\Ast\Group;
use AlgebraEngine\Math\Ast\Neg;
use AlgebraEngine\Math\Ast\Num;
use AlgebraEngine\Math\Ast\Pow;
use AlgebraEngine\Math\Ast\VarRef;
use AlgebraEngine\Math\EquationParser;
use AlgebraEngine\Math\ExpressionParser;
use AlgebraEngine\Math\MathInputError;
use AlgebraEngine\Math\Polynomial;
use PHPUnit\Framework\TestCase;

final class ExpressionParserTest extends TestCase
{
    public function testUnaryMinusBindsLooserThanPower(): void
    {
        $n = ExpressionParser::parse('-x^2');
        self::assertInstanceOf(Neg::class, $n);
        self::assertInstanceOf(Pow::class, $n->operand);
        self::assertSame(2, $n->operand->exponent);
        self::assertSame('-x^2', Polynomial::fromNode($n)->toString());
    }

    public function testImplicitMultiplicationForms(): void
    {
        $n = ExpressionParser::parse('3(x+2)');
        self::assertInstanceOf(BinOp::class, $n);
        self::assertTrue($n->implicit);
        self::assertInstanceOf(Num::class, $n->left);
        self::assertInstanceOf(Group::class, $n->right);

        $n = ExpressionParser::parse('3x');
        self::assertInstanceOf(BinOp::class, $n);
        self::assertInstanceOf(VarRef::class, $n->right);

        self::assertSame('x^2-1', Polynomial::fromNode(ExpressionParser::parse('(x-1)(x+1)'))->toString());
        self::assertSame('x^2+x', Polynomial::fromNode(ExpressionParser::parse('x(x+1)'))->toString());
        self::assertSame('x*y', Polynomial::fromNode(ExpressionParser::parse('xy', ['x', 'y']))->toString());
        self::assertSame('2*x^2', Polynomial::fromNode(ExpressionParser::parse('2x^2'))->toString());
        self::assertSame('x^2+x', Polynomial::fromNode(ExpressionParser::parse('(x+1)x'))->toString());
    }

    public function testPrecedenceAndAssociativity(): void
    {
        self::assertSame('7', Polynomial::fromNode(ExpressionParser::parse('1+2*3'))->toString());
        self::assertSame('-4', Polynomial::fromNode(ExpressionParser::parse('2-3-3'))->toString());
        self::assertSame('1', Polynomial::fromNode(ExpressionParser::parse('8/4/2'))->toString());
        self::assertSame('3/2*x', Polynomial::fromNode(ExpressionParser::parse('3x/2'))->toString());
        self::assertSame('-6', Polynomial::fromNode(ExpressionParser::parse('2*-3'))->toString());
        self::assertSame('x', Polynomial::fromNode(ExpressionParser::parse('--x'))->toString());
        self::assertSame("-x+1", Polynomial::fromNode(ExpressionParser::parse("1 \u{2212} x"))->toString());
        self::assertSame('6', Polynomial::fromNode(ExpressionParser::parse("2\u{00D7}3"))->toString());
    }

    /** @return array<string, array{0:string}> */
    public static function hostile(): array
    {
        return [
            'php call' => ["system('ls')"],
            'php tag' => ['<?php echo 1; ?>'],
            'huge exponent' => ['x^99999'],
            'negative exponent' => ['x^-1'],
            'fractional exponent' => ['x^1.5'],
            'stacked exponent' => ['x^2^3'],
            'eval' => ['eval(1)'],
            'template injection' => ['${x}'],
            'shell' => ['x;rm -rf /'],
            'ten thousand parens' => [str_repeat('(', 10000) . 'x' . str_repeat(')', 10000)],
            'deep but short parens' => [str_repeat('(', 30) . 'x' . str_repeat(')', 30)],
            'five thousand digits' => [str_repeat('9', 5000)],
            'thirteen digits' => ['1234567890123x'],
            'equation' => ['3x=6'],
            'unknown variable' => ['y+1'],
            'function word' => ['sqrt(x)'],
            'number after variable' => ['x2'],
            'number after paren' => ['(x+1)2'],
            'two numbers' => ['3 4'],
            'dangling operator' => ['3+'],
            'empty parens' => ['()'],
            'unbalanced' => ['(x+1'],
            'empty' => [''],
            'html' => ['<script>alert(1)</script>'],
            'many tokens' => [implode('+', array_fill(0, 120, 'x'))],
        ];
    }

    /** @dataProvider hostile */
    public function testHostileInputFailsClosed(string $input): void
    {
        $this->expectException(MathInputError::class);
        ExpressionParser::parse($input);
    }

    public function testSpecificErrorCodes(): void
    {
        $cases = [
            'x^99999' => 'EXPONENT_TOO_LARGE',
            'x^-1' => 'EXPONENT_NOT_ALLOWED',
            str_repeat('(', 26) . 'x' . str_repeat(')', 26) => 'TOO_DEEP',
            'sqrt(x)' => 'UNSUPPORTED_WORD',
            'y' => 'UNSUPPORTED_VARIABLE',
            '3x=6' => 'NOT_AN_EXPRESSION',
            'x;1' => 'UNSUPPORTED_CHARACTER',
            '1234567890123' => 'TOO_MANY_DIGITS',
            'x2' => 'MALFORMED',
        ];
        foreach ($cases as $input => $code) {
            try {
                ExpressionParser::parse((string) $input);
                self::fail("expected $code for $input");
            } catch (MathInputError $e) {
                self::assertSame($code, $e->errorCode, (string) $input);
            }
        }
        // Depth exactly at the limit is accepted.
        self::assertSame('x', Polynomial::fromNode(ExpressionParser::parse(str_repeat('(', 25) . 'x' . str_repeat(')', 25)))->toString());
    }

    public function testDivisionByZeroParsesButPolynomialRejects(): void
    {
        $node = ExpressionParser::parse('1/0');
        try {
            Polynomial::fromNode($node);
            self::fail('expected ZERO_DENOMINATOR');
        } catch (MathInputError $e) {
            self::assertSame('ZERO_DENOMINATOR', $e->errorCode);
        }
        try {
            Polynomial::fromNode(ExpressionParser::parse('1/x'));
            self::fail('expected NOT_POLYNOMIAL');
        } catch (MathInputError $e) {
            self::assertSame('NOT_POLYNOMIAL', $e->errorCode);
        }
    }

    public function testEquationParser(): void
    {
        [$l, $r] = EquationParser::parse('3(x + 4) = 27');
        self::assertSame('3*x+12', Polynomial::fromNode($l)->toString());
        self::assertSame('27', Polynomial::fromNode($r)->toString());
        foreach (['3x+4', 'x=1=2', '=5', 'x='] as $bad) {
            try {
                EquationParser::parse($bad);
                self::fail('expected NOT_AN_EQUATION for ' . $bad);
            } catch (MathInputError $e) {
                self::assertSame('NOT_AN_EQUATION', $e->errorCode);
            }
        }
    }
}
