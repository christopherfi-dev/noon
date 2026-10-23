<?php
declare(strict_types=1);

namespace AlgebraEngine\Tests\Integration;

use AlgebraEngine\Checking\ExactRationalChecker;
use AlgebraEngine\Checking\PolynomialChecker;
use AlgebraEngine\Math\Rational;
use AlgebraEngine\Tests\Support\Harness;
use PHPUnit\Framework\TestCase;

/** Acceptance scenarios MATH-01, 02, 03, 06, 08, 09 through the round 6 loop: code checks every answer. */
final class MathScenarioTest extends TestCase
{
    private function lastVerdict(Harness $h): ?string
    {
        $a = $h->attempts();
        return $a === [] ? null : end($a)['verdict'];
    }

    /** MATH-01: 0.5 for an expected 1/2 is correct; exact equivalent forms of a key are accepted. */
    public function testMath01ExactRational(): void
    {
        $this->assertSame('CORRECT', ExactRationalChecker::check(Rational::of(1, 2), '0.5')->verdict);
        $this->assertSame('CORRECT', ExactRationalChecker::check(Rational::of(-3, 4), '-6/8')->verdict);
        $h = new Harness();
        $h->start('Q06');
        $h->send($h->answer() . '.0');
        $this->assertSame('CORRECT', $this->lastVerdict($h));
        $h2 = new Harness();
        $h2->start('Q06');
        $h2->send('x = ' . (2 * (int) $h2->answer()) . '/2');
        $this->assertSame('CORRECT', $this->lastVerdict($h2));
    }

    /** MATH-02: 8 for an expected 7 is incorrect, whatever the edit distance. */
    public function testMath02NearNumber(): void
    {
        $this->assertSame('INCORRECT', ExactRationalChecker::check(Rational::fromInt(7), '8')->verdict);
        $h = new Harness();
        $h->start('Q04');
        $h->send((string) ((int) $h->answer() + 1));
        $this->assertSame('INCORRECT', $this->lastVerdict($h));
    }

    /** MATH-03: expanding a(x+b): the full expansion is correct, the factor on one term is incorrect; no sampling. */
    public function testMath03PolynomialExpansion(): void
    {
        $ok = PolynomialChecker::check('3*x+6', '3x+6', ['x'], 'expanded');
        $bad = PolynomialChecker::check('3*x+6', '3x+2', ['x'], 'expanded');
        $this->assertSame(['CORRECT', 'INCORRECT'], [$ok->verdict, $bad->verdict]);
        $this->assertStringContainsString('no sampling', $ok->scope);
        $h = new Harness();
        $h->start('E06');
        $p = $h->open()['params'];
        $h->send($p['a'] . 'x+' . $p['b']);
        $this->assertSame('INCORRECT', $this->lastVerdict($h));
        $this->assertSame(['FIRST_TERM_ONLY'], $h->attempts()[0]['signatures']);
        $h2 = new Harness();
        $h2->start('E06');
        $p = $h2->open()['params'];
        $h2->send(($p['a'] * $p['b']) . ' + ' . $p['a'] . 'x');
        $this->assertSame('CORRECT', $this->lastVerdict($h2));
    }

    /** MATH-06: an equivalent answer not in the requested form is PARTIAL, not correct and not a failure. */
    public function testMath06RequestedForm(): void
    {
        $f = PolynomialChecker::check('(x-1)*(x+1)', 'x^2-1', ['x'], 'factored');
        $this->assertSame(['PARTIAL', 'NOT_A_PRODUCT'], [$f->verdict, $f->formIssue]);
        $h = new Harness();
        $h->start('E06');
        $p = $h->open()['params'];
        $h->send($p['a'] . '(x+' . $p['b'] . ')');
        $this->assertSame('PARTIAL', $this->lastVerdict($h));
        $results = array_values(array_filter($h->lines(), fn ($l) => $l['kind'] === 'result'));
        $this->assertStringStartsWith('Equal, but not in the form asked.', $results[0]['text']);
        $this->assertSame(0, $h->goal('E06')['count']);
    }

    /** MATH-08: code, huge exponents, zero denominators and deep nesting are never executed; nothing is recorded as wrong. */
    public function testMath08UnsafeSyntax(): void
    {
        $h = new Harness();
        $h->start('E06');
        $problem = $h->view['open_problem']['problem_id'];
        $inputs = ["system('ls')", '<?php echo 1; ?>', 'x^99999', '1/0', str_repeat('(', 30) . 'x' . str_repeat(')', 30), str_repeat('9', 400), '${x}', 'x;rm -rf /'];
        foreach ($inputs as $text) {
            $r = $h->send($text);
            $this->assertSame(200, $r->status, $text);
            $this->assertSame($problem, $h->view['open_problem']['problem_id'] ?? null, 'the same problem stays open: ' . $text);
        }
        $this->assertSame([], array_filter($h->attempts(), fn ($a) => $a['verdict'] === 'INCORRECT'), 'unreadable input is never recorded as a wrong answer');
    }

    /** MATH-09: ambiguous mixed-number notation is not graded: the AI reads it (round 7 §7.4), code rejects a reading that drops part of it, the AI asks; then a clean answer is checked. */
    public function testMath09Ambiguity(): void
    {
        $h = new Harness();
        $h->start('Q05');
        $problem = $h->view['open_problem']['problem_id'];
        $h->send('1 1/2');
        $this->assertSame([], $h->attempts());
        $this->assertCount(1, $h->eventsOf('READING_REJECTED'));
        $this->assertSame('tutor', end($h->view['conversation'])['role'], 'the AI asks for a rewrite; code writes no line');
        $h->send('[1, 2');
        $this->assertSame([], $h->attempts());
        $h->answerCorrectly();
        $this->assertSame('CORRECT', $this->lastVerdict($h));
        $this->assertSame($problem, $h->attempts()[0]['problem_id'], 'a rewrite of the same problem is not a new problem');
    }
}
