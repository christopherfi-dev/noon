<?php
declare(strict_types=1);

namespace AlgebraEngine\Tests\Unit\Math;

use AlgebraEngine\Checking\ExactRationalChecker;
use AlgebraEngine\Checking\PolynomialChecker;
use AlgebraEngine\Math\ScalarParser;
use PHPUnit\Framework\TestCase;

/** Golden expected outcomes from examples/checker-golden-cases.json. */
final class GoldenCasesTest extends TestCase
{
    /** Golden ids whose checkers are outside the beta (solution sets, rational domains, intervals, graphs). */
    public const NOT_COVERED_BY_BETA_LIBRARY = ['ROOT-01', 'ROOT-02', 'ROOT-03', 'DOMAIN-01', 'INTERVAL-01', 'GRAPH-01'];

    /** @return array<string, array<string, mixed>> */
    private static function cases(): array
    {
        $data = json_decode((string) file_get_contents(ALGEBRA_REPO_ROOT . '/examples/checker-golden-cases.json'), true, 32, JSON_THROW_ON_ERROR);
        $byId = [];
        foreach ($data['cases'] as $case) {
            $byId[$case['id']] = $case;
        }
        return $byId;
    }

    public function testRationalGoldens(): void
    {
        $cases = self::cases();
        foreach (['RAT-01', 'RAT-02', 'RAT-03', 'RAT-04'] as $id) {
            $c = $cases[$id];
            $out = ExactRationalChecker::check(ScalarParser::parse($c['expected']), $c['response']);
            self::assertSame($c['verdict'], $out->verdict, $id);
        }
    }

    public function testPolynomialGoldens(): void
    {
        $cases = self::cases();
        foreach (['POLY-01', 'POLY-02'] as $id) {
            $c = $cases[$id];
            $out = PolynomialChecker::check($c['expected'], $c['response'], ['x'], null);
            self::assertSame($c['verdict'], $out->verdict, $id);
        }
        // POLY-01's declared scope is equivalence only; the checker says the form was not assessed.
        self::assertStringContainsString('requested form not assessed', PolynomialChecker::check('3*x+6', '3*(x+2)', ['x'], null)->scope);
    }

    public function testRequestedFormGolden(): void
    {
        $c = self::cases()['FORM-01'];
        self::assertSame('Factor x^2-1', $c['task']);
        $out = PolynomialChecker::check('x^2-1', $c['response'], ['x'], 'factored');
        self::assertSame($c['verdict'], $out->verdict);
    }

    public function testOutOfScopeGoldensAreListedNotGuessed(): void
    {
        $cases = self::cases();
        foreach (self::NOT_COVERED_BY_BETA_LIBRARY as $id) {
            self::assertArrayHasKey($id, $cases, $id);
        }
        $covered = ['RAT-01', 'RAT-02', 'RAT-03', 'RAT-04', 'POLY-01', 'POLY-02', 'FORM-01'];
        self::assertEqualsCanonicalizing(array_keys($cases), array_merge($covered, self::NOT_COVERED_BY_BETA_LIBRARY));
    }
}
