<?php
declare(strict_types=1);

namespace AlgebraEngine\Tests\Integration;

use AlgebraEngine\Tests\Support\Harness;
use PHPUnit\Framework\TestCase;

/**
 * B16 (round 5.1, P4): an answer to a diagnostic probe given after a hint is
 * inconclusive. One more fresh probe follows; correct with no help means the
 * error was not reproduced; wrong, or answered after a hint or a stuck entry,
 * confirms the breakdown and descends along the original error's branch.
 * No more than two probes per diagnosis.
 */
final class ProbeAfterHelpTest extends TestCase
{
    /** Q07 target failed with the distribution signature (branch E06); the first probe answered after a hint. */
    private static function inconclusiveProbe(string $seed, bool $wrongFirst = false): Harness
    {
        $h = new Harness([], null, null, null, null, $seed);
        $h->start('Q07');
        $h->submit($h->wrong('DIST_X_ONLY'));
        self::assertSame('DIAGNOSTIC_PROBE', $h->mode());
        $first = $h->active()['item_id'];
        self::assertSame(200, $h->event('REQUEST_HINT')->status);
        $wrongFirst ? $h->submit($h->plainWrong()) : $h->answerCorrect();
        self::assertSame('DIAGNOSTIC_PROBE', $h->mode(), 'still diagnosing');
        $obs = $h->events('OBSERVATION');
        self::assertSame(['PROBE_INCONCLUSIVE_AFTER_HELP', 'Answered after a hint; inconclusive.'], [end($obs)['data']['code'], end($obs)['data']['statement']]);
        self::assertNotSame($first, $h->active()['item_id'], 'a fresh second probe');
        self::assertSame(['DIAGNOSTIC', 'INDEPENDENT'], [$h->active()['role'], $h->view['active_item']['support_status']]);
        self::assertSame([], $h->events('DESCENT'), 'no descent yet');
        self::assertSame([], array_filter($h->events('OBSERVATION'), fn ($e) => $e['data']['code'] === 'ERROR_NOT_REPRODUCED'), 'not read as a slip');
        return $h;
    }

    private static function probesPresented(Harness $h): int
    {
        return count(array_filter($h->events('ITEM_PRESENTED'), fn ($e) => $e['data']['role'] === 'DIAGNOSTIC'));
    }

    /** Outcome 1: the second probe is correct with no help → not reproduced; resume target assessment as now. */
    public function testSecondProbeCorrectWithoutHelpResumes(): void
    {
        foreach ([false, true] as $wrongFirst) {               // a first answer after a hint is inconclusive whether right or wrong
            $h = self::inconclusiveProbe('b16-resume-' . (int) $wrongFirst, $wrongFirst);
            try {
                $h->answerCorrect();
                $this->assertSame(['TARGET_ASSESSMENT', 'Q07'], [$h->mode(), $h->skill()]);
                $obs = $h->events('OBSERVATION');
                $this->assertSame('ERROR_NOT_REPRODUCED', end($obs)['data']['code']);
                $this->assertStringStartsWith('The earlier error was not reproduced on a fresh probe', end($obs)['data']['statement']);
                $this->assertSame([], $h->events('DESCENT'));
                $this->assertSame(2, self::probesPresented($h));
            } finally {
                $h->cleanup();
            }
        }
    }

    /** Outcome 2: the second probe is wrong → breakdown confirmed; descend along the original error's branch (E06). */
    public function testSecondProbeWrongDescends(): void
    {
        $h = self::inconclusiveProbe('b16-wrong');
        try {
            $h->submit($h->plainWrong());                        // no signature of its own: the original branch is used
            $this->assertSame(['PREREQUISITE_CHECK', 'E06'], [$h->mode(), $h->skill()]);
            $d = $h->events('DESCENT')[0]['data'];
            $this->assertSame(['Q07', 'E06'], [$d['from_skill'], $d['to_skill']]);
            $this->assertSame(2, self::probesPresented($h));
        } finally {
            $h->cleanup();
        }
    }

    /** Outcome 3: the second probe is answered after a hint, or after a stuck entry → breakdown confirmed; descend. */
    public function testSecondProbeAfterHelpDescends(): void
    {
        foreach (['hint' => fn (Harness $h) => $h->event('REQUEST_HINT'), 'stuck entry' => fn (Harness $h) => $h->event('SUBMIT_RESPONSE', ['text' => 'idk'])] as $how => $help) {
            $h = self::inconclusiveProbe('b16-help-' . $how);
            try {
                $this->assertSame(200, $help($h)->status, $how);
                $this->assertSame('ASSISTED', $h->view['active_item']['support_status'], $how);
                $h->answerCorrect();                             // even a correct answer: it was given after help
                $this->assertSame(['PREREQUISITE_CHECK', 'E06'], [$h->mode(), $h->skill()], $how);
                $obs = array_column(array_column($h->events('OBSERVATION'), 'data'), 'code');
                $this->assertContains('BREAKDOWN_CONFIRMED_AFTER_HELP', $obs, $how);
                $this->assertNotContains('ERROR_NOT_REPRODUCED', $obs, $how);
                $this->assertSame([], array_filter($h->events('HYPOTHESIS'), fn ($e) => $e['data']['status'] === 'SUPPORTED_FOR_OBSERVED_PATTERN'), 'assisted work changes no hypothesis');
                $this->assertSame(2, self::probesPresented($h), 'no more than two probes');
            } finally {
                $h->cleanup();
            }
        }
    }

    /** No more than two probes per diagnosis, also when probes are skipped. */
    public function testAtMostTwoProbesWhenSkipped(): void
    {
        $h = new Harness([], null, null, null, null, 'b16-skip');
        try {
            $h->start('Q07');
            $h->submit($h->wrong('DIST_X_ONLY'));
            $h->event('SKIP_ITEM');
            $this->assertSame('DIAGNOSTIC_PROBE', $h->mode());
            $h->event('SKIP_ITEM');
            $this->assertSame('GUIDED_REPAIR', $h->mode(), 'probe budget reached; bounded instructional trial');
            $this->assertSame(2, self::probesPresented($h));
        } finally {
            $h->cleanup();
        }
    }
}
