<?php
declare(strict_types=1);

namespace AlgebraEngine\Tests\Integration;

use AlgebraEngine\Checking\ExactRationalChecker;
use AlgebraEngine\Checking\PolynomialChecker;
use AlgebraEngine\Math\Rational;
use AlgebraEngine\Tests\Support\Harness;
use PHPUnit\Framework\TestCase;

/** Acceptance scenarios MATH-01, 02, 03, 06, 08, 09 — checker level and through the live API. */
final class MathScenarioTest extends TestCase
{
    private Harness $h;

    protected function setUp(): void
    {
        $this->h = new Harness();
    }

    protected function tearDown(): void
    {
        $this->h->cleanup();
    }

    private function lastVerdict(): string
    {
        $r = $this->h->events('CHECKER_RESULT');
        return end($r)['data']['result']['verdict'];
    }

    /** MATH-01: 0.5 for an expected 1/2 is correct; exact equivalent forms of an integer key are accepted. */
    public function testMath01ExactRational(): void
    {
        $this->assertSame('CORRECT', ExactRationalChecker::check(Rational::of(1, 2), '0.5')->verdict);
        $this->assertSame('CORRECT', ExactRationalChecker::check(Rational::of(-3, 4), '-6/8')->verdict);
        $h = $this->h;
        $h->start('Q06');
        $key = $h->correct();
        $h->submit($key . '.0');
        $this->assertSame('CORRECT', $this->lastVerdict());
        $key = $h->correct();
        $h->submit('x = ' . (2 * (int) $key) . '/2');
        $this->assertSame('CORRECT', $this->lastVerdict());
    }

    /** MATH-02: 8 for an expected 7 is incorrect, whatever the edit distance. */
    public function testMath02NearNumber(): void
    {
        $this->assertSame('INCORRECT', ExactRationalChecker::check(Rational::fromInt(7), '8')->verdict);
        $h = $this->h;
        $h->start('Q04');
        $near = (string) ((int) $h->correct() + 1);
        $h->submit($near);
        $this->assertSame('INCORRECT', $this->lastVerdict());
    }

    /** MATH-03: expanding a(x+b): the full expansion is correct, the factor on one term is incorrect; no sampling. */
    public function testMath03PolynomialExpansion(): void
    {
        $ok = PolynomialChecker::check('3*x+6', '3x+6', ['x'], 'expanded');
        $bad = PolynomialChecker::check('3*x+6', '3x+2', ['x'], 'expanded');
        $this->assertSame(['CORRECT', 'INCORRECT'], [$ok->verdict, $bad->verdict]);
        $this->assertStringContainsString('no sampling', $ok->scope);
        $h = $this->h;
        $h->start('E06');
        $p = $h->active()['params'];
        $h->submit($p['a'] . 'x+' . $p['b']);
        $this->assertSame('INCORRECT', $this->lastVerdict());
        $h2 = new Harness();
        $h2->start('E06');
        $p = $h2->active()['params'];
        $h2->submit(($p['a'] * $p['b']) . ' + ' . $p['a'] . 'x');
        $results = $h2->events('CHECKER_RESULT');
        $this->assertSame('CORRECT', $results[0]['data']['result']['verdict']);
        $h2->cleanup();
        $this->assertStringContainsString('distribution-to-first-term-only', $h->events('OBSERVATION')[0]['data']['statement']);
    }

    /** MATH-06: an equivalent answer that is not in the requested form is PARTIAL, not correct and not a failure. */
    public function testMath06RequestedForm(): void
    {
        $f = PolynomialChecker::check('(x-1)*(x+1)', 'x^2-1', ['x'], 'factored');
        $this->assertSame(['PARTIAL', 'NOT_A_PRODUCT'], [$f->verdict, $f->formIssue]);
        $h = $this->h;
        $h->start('E06');
        $p = $h->active()['params'];
        $h->submit($p['a'] . '(x+' . $p['b'] . ')');
        $this->assertSame('PARTIAL', $this->lastVerdict());
        $this->assertStringContainsString('still has parentheses', $h->view['messages'][0]['text']);
        $this->assertSame('TARGET_ASSESSMENT', $h->mode(), 'form issue is not routed as an error');
        $this->assertSame(0, $h->view['progress']['independent_successes']);
    }

    /** MATH-08: code, huge exponents, zero denominators and deep nesting are never executed; unscorable with clarification. */
    public function testMath08UnsafeSyntax(): void
    {
        $h = $this->h;
        $h->start('E06');
        $item = $h->view['active_item']['item_id'];
        $inputs = ["system('ls')", '<?php echo 1; ?>', 'x^99999', '1/0', str_repeat('(', 30) . 'x' . str_repeat(')', 30), str_repeat('9', 400), '${x}', 'x;rm -rf /'];
        foreach ($inputs as $text) {
            $r = $h->submit($text);
            $this->assertSame('UNSCORABLE', $this->lastVerdict(), $text);
            $this->assertSame('ERROR', $r->body['messages'][0]['kind']);
            $this->assertSame($item, $h->view['active_item']['item_id'], 'same item stays active');
        }
        $this->assertSame('TARGET_ASSESSMENT', $h->mode());
        $this->assertSame([], array_filter($h->events('CHECKER_RESULT'), fn ($e) => $e['data']['result']['verdict'] === 'INCORRECT'));
        $this->assertContains('CLARIFY_RESPONSE', $h->view['allowed_actions']);
    }

    /** MATH-09: ambiguous mixed-number notation or a malformed interval asks for clarification and keeps the item. */
    public function testMath09Ambiguity(): void
    {
        $h = $this->h;
        $h->start('Q05');
        $item = $h->view['active_item']['item_id'];
        $r = $h->submit('1 1/2');
        $this->assertSame('UNSCORABLE', $this->lastVerdict());
        $this->assertStringContainsString('can be read more than one way', $r->body['messages'][0]['text']);
        $h->submit('[1, 2');
        $this->assertSame('UNSCORABLE', $this->lastVerdict());
        $r = $h->event('CLARIFY_RESPONSE', ['text' => $h->correct()]);
        $this->assertSame(200, $r->status);
        $this->assertSame('CORRECT', $this->lastVerdict());
        $results = $h->events('CHECKER_RESULT');
        $this->assertSame($item, end($results)['data']['result']['item_id'], 'a clarification of the same item is not a new item');
        $this->assertSame(1, $h->view['progress']['independent_successes']);
    }
}
