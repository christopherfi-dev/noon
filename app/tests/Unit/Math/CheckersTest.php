<?php
declare(strict_types=1);

namespace AlgebraEngine\Tests\Unit\Math;

use AlgebraEngine\Checking\ClassificationChecker;
use AlgebraEngine\Checking\ExactRationalChecker;
use AlgebraEngine\Checking\PolynomialChecker;
use AlgebraEngine\Math\Rational;
use PHPUnit\Framework\TestCase;

final class CheckersTest extends TestCase
{
    public function testExactRational(): void
    {
        $seven = Rational::fromInt(7);
        self::assertSame('CORRECT', ExactRationalChecker::check($seven, '7')->verdict);
        self::assertSame('CORRECT', ExactRationalChecker::check($seven, 'x = 7')->verdict);
        self::assertSame('CORRECT', ExactRationalChecker::check($seven, '14/2')->verdict);
        // MATH-02: near numbers are incorrect regardless of edit distance.
        $near = ExactRationalChecker::check($seven, '8');
        self::assertSame('INCORRECT', $near->verdict);
        self::assertSame('8', $near->normalizedResponse);
        self::assertSame('INCORRECT', ExactRationalChecker::check($seven, '-7')->verdict);
        self::assertSame('INCORRECT', ExactRationalChecker::check($seven, '71')->verdict);

        $unscorable = ExactRationalChecker::check($seven, '1 1/2');
        self::assertSame('UNSCORABLE', $unscorable->verdict);
        self::assertNotNull($unscorable->clarification);
        self::assertSame(ExactRationalChecker::SCOPE, $unscorable->scope);
        self::assertSame('UNSCORABLE', ExactRationalChecker::check($seven, "<script>alert('x')</script>")->verdict);
        self::assertSame('UNSCORABLE', ExactRationalChecker::check($seven, 'seven')->verdict);
    }

    public function testPolynomialEquivalenceAndForm(): void
    {
        // MATH-03: expand 3(x+2).
        $ok = PolynomialChecker::check('3*x+6', '3x+6', ['x'], 'expanded');
        self::assertSame('CORRECT', $ok->verdict);
        self::assertSame('3*x+6', $ok->normalizedResponse);
        self::assertStringContainsString('no sampling', $ok->scope);
        self::assertSame('INCORRECT', PolynomialChecker::check('3*x+6', '3x+2', ['x'], 'expanded')->verdict);

        $grouped = PolynomialChecker::check('3*x+6', '3(x+2)', ['x'], 'expanded');
        self::assertSame('PARTIAL', $grouped->verdict);
        self::assertSame('STILL_GROUPED', $grouped->formIssue);

        $notMultiplied = PolynomialChecker::check('3*x+6', '3x+3*2', ['x'], 'expanded');
        self::assertSame('PARTIAL', $notMultiplied->verdict);
        self::assertSame('NUMBERS_NOT_MULTIPLIED', $notMultiplied->formIssue);

        // MATH-06: factor x^2-1; the unfactored expression is equivalent but not in the requested form.
        $form = PolynomialChecker::check('(x-1)(x+1)', 'x^2-1', ['x'], 'factored');
        self::assertSame('PARTIAL', $form->verdict);
        self::assertSame('NOT_A_PRODUCT', $form->formIssue);
        self::assertSame('CORRECT', PolynomialChecker::check('x^2-1', '(x+1)(x-1)', ['x'], 'factored')->verdict);

        $noForm = PolynomialChecker::check('3*x+6', '3*(x+2)', ['x'], null);
        self::assertSame('CORRECT', $noForm->verdict);
        self::assertStringContainsString('requested form not assessed', $noForm->scope);
        self::assertNotEmpty($noForm->limitations);

        $eq = PolynomialChecker::check('3*x+6', '3x+6=0', ['x'], 'expanded');
        self::assertSame('UNSCORABLE', $eq->verdict);
        self::assertNotNull($eq->clarification);
        self::assertSame('UNSCORABLE', PolynomialChecker::check('3*x+6', 'x^99999', ['x'], 'expanded')->verdict);
        self::assertSame('UNSCORABLE', PolynomialChecker::check('3*x+6', '1/(x+2)', ['x'], 'expanded')->verdict);
    }

    public function testPolynomialCheckerRejectsInvalidHostKey(): void
    {
        $this->expectException(\LogicException::class);
        PolynomialChecker::check('3x+', '3x', ['x'], null);
    }

    public function testClassification(): void
    {
        $letters = ['A', 'B', 'C', 'D'];
        foreach (['A, C', 'a c', 'AC', 'A and C', 'A;C', 'c,a', 'A, C, A', '(A) (C)'] as $r) {
            $out = ClassificationChecker::check(['A', 'C'], $letters, $r);
            self::assertSame('CORRECT', $out->verdict, $r);
            self::assertSame('A,C', $out->normalizedResponse, $r);
        }
        self::assertSame('INCORRECT', ClassificationChecker::check(['A', 'C'], $letters, 'A')->verdict);
        self::assertSame('INCORRECT', ClassificationChecker::check(['A', 'C'], $letters, 'A, B, C')->verdict);
        foreach (['', 'E', 'option A', 'A, 1', 'both'] as $r) {
            $out = ClassificationChecker::check(['A', 'C'], $letters, $r);
            self::assertSame('UNSCORABLE', $out->verdict, $r);
            self::assertNotNull($out->clarification);
        }
    }
}
