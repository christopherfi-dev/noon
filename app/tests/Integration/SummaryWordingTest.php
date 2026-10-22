<?php
declare(strict_types=1);

namespace AlgebraEngine\Tests\Integration;

use AlgebraEngine\Report\ReportBuilder;
use AlgebraEngine\Support\LearnerText;
use AlgebraEngine\Tests\Support\Harness;
use PHPUnit\Framework\TestCase;

/**
 * Round 5.1 ruling 4: the session summary is learner-visible, so its prose
 * follows R5-B. Field names and stored codes (such as the criterion status)
 * are unchanged; every string with words in it is checked.
 */
final class SummaryWordingTest extends TestCase
{
    /** @return list<string> "path: term: text" for each prose string that uses an internal term */
    private static function hits(mixed $value, string $path = ''): array
    {
        if (is_string($value)) {
            if (!preg_match('/\s/', $value)) {
                return []; // codes and ids, not prose
            }
            return array_map(fn ($t) => "$path: $t: " . mb_substr($value, 0, 120), LearnerText::internalTerms($value));
        }
        $out = [];
        foreach (is_array($value) ? $value : [] as $k => $v) {
            $out = array_merge($out, self::hits($v, $path . '/' . $k));
        }
        return $out;
    }

    private static function summary(Harness $h): array
    {
        $r = $h->request('GET', 'report');
        self::assertSame(200, $r->status);
        return $r->body;
    }

    public function testSummaryProseUsesPlainWords(): void
    {
        $summaries = [];
        // A completed B1 journey: descent, beta boundary, guided work, transfer, two goals met.
        $h = new Harness([], null, null, null, null, 'wording-b1');
        try {
            $h->start('Q07');
            foreach (['DIST_X_ONLY', 'DIST_X_ONLY', 'FIRST_TERM_ONLY'] as $code) {
                $h->submit($h->wrong($code));
            }
            for ($n = 0; $n < 40 && $h->mode() !== 'SESSION_REPORT'; $n++) {
                $h->answerCorrect();
            }
            $this->assertSame('SESSION_REPORT', $h->mode());
            $s = self::summary($h);
            $this->assertStringContainsString('goal met: 3 correct in a row without help', $s['report']['immediate_criteria'][0]['statement']);
            $this->assertSame('IMMEDIATE_CRITERION_MET', $s['per_skill'][0]['criterion_status'], 'stored codes are unchanged');
            $this->assertArrayHasKey('assisted', $s['per_skill'][0], 'field names are unchanged');
            $summaries['B1'] = $s;
        } finally {
            $h->cleanup();
        }
        // B3 path with hints, an assisted independent check, a held generation and an interrupted topic.
        $h = new Harness([], null, null, null, null, 'wording-b3');
        try {
            $h->start('Q07');
            foreach (['SIGN_ERROR_B', 'SIGN_ERROR_B', 'MULTIPLY_INSTEAD_OF_DIVIDE', 'QUOTIENT_SIGN', 'NO_SIGNATURE'] as $code) {
                $h->submit($h->wrong($code));
            }
            $h->completeGuidedRepair();
            $h->event('REQUEST_HINT');
            $h->submit($h->plainWrong());                        // assisted wrong answer at the independent check
            for ($i = 0; $i < 3; $i++) {
                $h->mock->faults[] = ['type' => 'INVALID_JSON'];
            }
            $h->answerCorrect();
            $this->assertSame('HOLD', $h->view['status']);
            $h->event('CHANGE_TARGET', ['target_skill_id' => 'E06']);
            $summaries['B3'] = self::summary($h);
        } finally {
            $h->cleanup();
        }
        foreach ($summaries as $name => $s) {
            $this->assertSame([], self::hits($s), $name);
        }
    }

    public function testPlainWordsForStoredText(): void
    {
        foreach ([
            'Exact rational comparison with the host-derived key; no tolerance; reasoning not assessed.' => 'Exact rational comparison with the answer the app computed; no tolerance; reasoning not assessed.',
            'Repair criterion met at E06.' => 'Repair goal met (3 correct in a row without help) at E06.',
            'Check at E06 was assisted or not in the requested form; bounded instructional trial.' => 'Check at E06 was answered with help or not in the requested form; bounded instructional trial.',
            'Assisted independent-check attempt was not correct; continue guided work (not a failed cycle).' => 'An independent-check attempt answered with help was not correct; continue guided work (not a failed cycle).',
            'The provider output failed host acceptance after 2 repair attempts.' => 'The provider output failed app acceptance after 2 repair attempts.',
        ] as $stored => $plain) {
            $this->assertSame($plain, ReportBuilder::plain($stored));
            $this->assertSame([], LearnerText::internalTerms(ReportBuilder::plain($stored)));
        }
    }
}
