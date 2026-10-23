<?php
declare(strict_types=1);

namespace AlgebraEngine\Tests\Unit\Items;

use AlgebraEngine\Checking\ClassificationChecker;
use AlgebraEngine\Checking\ExactRationalChecker;
use AlgebraEngine\Checking\PolynomialChecker;
use AlgebraEngine\Items\ItemFactory;
use AlgebraEngine\Math\EquationParser;
use AlgebraEngine\Math\LinearEquation;
use AlgebraEngine\Math\Rational;
use AlgebraEngine\Math\ScalarParser;
use AlgebraEngine\Support\RandomSource;
use PHPUnit\Framework\TestCase;

/** The 14 recipes (server-owned templates): code's answers, bounds, signatures and contrast keys. Round 6 uses them as problem generators and, in reverse, as check patterns. */
final class TemplatesTest extends TestCase
{
    private static function check(array $spec, array $objects, string $response): string
    {
        return match ($spec['kind']) {
            'exact_rational' => ExactRationalChecker::check(ScalarParser::parse($spec['expected']), $response)->verdict,
            'polynomial' => PolynomialChecker::check($spec['expected'], $response, $objects['variables'] ?? ['x'], $spec['requested_form'] === 'expanded' ? 'expanded' : null)->verdict,
            'classification' => ClassificationChecker::check($spec['expected'], $objects['option_letters'], $response)->verdict,
        };
    }

    public function testEveryTemplateGeneratesValidItemsWhoseHostKeyChecksCorrect(): void
    {
        $f = new ItemFactory();
        $this->assertCount(14, $f->all());
        foreach ($f->all() as $id => $t) {
            $rng = RandomSource::seeded('t' . $id);
            for ($i = 0; $i < 120; $i++) {
                $p = $t->generate($rng);
                $this->assertSame([], $t->validate($p), $id);
                $spec = $t->answerSpec($p);
                $answer = is_array($spec['expected']) ? implode(',', $spec['expected']) : $spec['expected'];
                $this->assertSame('CORRECT', self::check($spec, $t->mathematicalObjects($p), $answer), $id . ' ' . json_encode($p));
                $this->assertNotSame('', $t->render($p));
            }
        }
    }

    public function testParameterBoundsAreEnforced(): void
    {
        $f = new ItemFactory();
        $q07 = $f->get('GROUPED_LINEAR');
        $this->assertNotSame([], $q07->validate(['a' => 1, 'b' => 2, 'c' => 9]));
        $this->assertNotSame([], $q07->validate(['a' => 3, 'b' => 0, 'c' => 9]));
        $this->assertNotSame([], $q07->validate(['a' => 3, 'b' => 2, 'c' => 10]), 'non-integer solution');
        $this->assertNotSame([], $q07->validate(['a' => 3, 'b' => 2, 'c' => 9, 'evil' => 1]), 'unknown parameter');
        $this->assertNotSame([], $q07->validate(['a' => '3', 'b' => 2, 'c' => 9]), 'type');
        $this->assertSame([], $q07->validate(['a' => 3, 'b' => 4, 'c' => 27]));
        $this->assertNotSame([], $f->get('SIGNED_DIVIDE')->validate(['a' => 7, 'b' => -2]));
        $this->assertNotSame([], $f->get('POSITIVE_DISTRIBUTION')->validate(['a' => -3, 'b' => 2]));
    }

    public function testGroupedLinearSignaturesAreDistinctAndBranchCorrectly(): void
    {
        $t = (new ItemFactory())->get('GROUPED_LINEAR');
        $p = ['a' => 3, 'b' => 4, 'c' => 27];  // 3(x+4)=27, x=5
        $sig = fn (string $r) => array_column($t->matchSignatures($p, $r, ExactRationalChecker::check(Rational::fromInt(5), $r)), 'branch', 'code');
        $this->assertSame(['DIST_X_ONLY' => 'E06'], $sig('23/3'));      // 3x+4=27
        $this->assertSame(['SIGN_ERROR_B' => 'Q06', 'DIVIDE_THEN_ADD_B' => 'Q06'], $sig('13'));
        $this->assertSame(['ARITH_FINAL_PLUS' => 'Q06'], $sig('6'));
        $this->assertSame([], $sig('40'));
        $this->assertSame([], $sig('5'), 'no signature for a correct answer');
        $this->assertSame('Your answer multiplied only the x by 3.', $t->signatureFeedback($p, 'DIST_X_ONLY'));
    }

    public function testContrastKeysAreDerivedFromTheDisplayedEquations(): void
    {
        $t = (new ItemFactory())->get('GROUPED_FIRST_STEP_CONTRAST');
        $p = ['a' => 3, 'b' => 4, 'c' => 27, 'options' => ['MULTIPLY_X_ONLY', 'DIVIDE_BOTH', 'SUBTRACT_INSIDE', 'DISTRIBUTE']];
        $this->assertSame(['B', 'D'], $t->answerSpec($p)['expected']);
        // Independently re-solve every displayed option line.
        [$l, $r] = EquationParser::parse('3(x + 4) = 27');
        $original = LinearEquation::solve($l, $r)->value;
        foreach (explode("\n", $t->render($p)) as $line) {
            if (preg_match('/^([A-D])\) .*: (.*)$/u', $line, $m)) {
                [$ol, $or] = EquationParser::parse($m[2]);
                $same = LinearEquation::solve($ol, $or)->value->equals($original);
                $this->assertSame(in_array($m[1], ['B', 'D'], true), $same, $line);
            }
        }
        $this->assertNotSame([], $t->validate(['a' => 3, 'b' => 4, 'c' => 27, 'options' => ['MULTIPLY_X_ONLY', 'SUBTRACT_INSIDE', 'DIVIDE_LEFT_ONLY', 'MULTIPLY_X_ONLY']]));
        $this->assertNotSame([], $t->validate(['a' => 3, 'b' => 4, 'c' => 27, 'options' => ['MULTIPLY_X_ONLY', 'SUBTRACT_INSIDE', 'DIVIDE_LEFT_ONLY']]));
        $d = (new ItemFactory())->get('DISTRIBUTION_CONTRAST');
        $this->assertSame(['A', 'D'], $d->answerSpec(['a' => 4, 'b' => 3, 'options' => ['EXPANDED', 'FIRST_ONLY', 'SECOND_ONLY', 'UNSIMPLIFIED']])['expected']);
    }

    public function testOptionOrderIsRandomisedPerItem(): void
    {
        $t = (new ItemFactory())->get('GROUPED_FIRST_STEP_CONTRAST');
        $rng = RandomSource::seeded('order');
        $orders = [];
        for ($i = 0; $i < 30; $i++) {
            $orders[] = implode(',', $t->generate($rng)['options']);
        }
        $this->assertGreaterThan(5, count(array_unique($orders)));
    }
}
