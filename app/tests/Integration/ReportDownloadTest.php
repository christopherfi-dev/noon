<?php
declare(strict_types=1);

namespace AlgebraEngine\Tests\Integration;

use AlgebraEngine\Report\SummaryView;
use AlgebraEngine\Tests\Support\Harness;
use PHPUnit\Framework\TestCase;

/** Conductor round UI v3: the report's empty headings (U2) and the downloadable file (U9). */
final class ReportDownloadTest extends TestCase
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

    /** U2: each heading with nothing to report shows its fixed line; nothing is invented to fill it. */
    public function testEmptyHeadingsUseTheirFixedLines(): void
    {
        $this->assertSame([
            'did_well' => 'No goals were met in this session yet.',
            'trouble' => 'No trouble spots were recorded in this session.',
            'worked_on' => 'This session had no practice with help.',
            'next_time' => 'You met every goal available in this tutor so far. Ask your teacher what comes next.',
        ], SummaryView::EMPTY);
        $this->assertSame(['What you did well', 'Where you had trouble', 'What we worked on', 'What to work on next time'], array_values(SummaryView::HEADINGS));

        // A session ended before any answer: nothing done well, no trouble, no practice with help.
        $h = $this->h;
        $h->start('Q07');
        $h->event('END_SESSION');
        $learner = $h->request('GET', 'report')->body['summary']['learner'];
        $this->assertSame([[], [], []], [$learner['did_well'], $learner['trouble'], $learner['worked_on']]);
        $this->assertSame(['Solve equations containing parentheses: aim for 3 correct in a row without help, including a solve, a step choice and a new kind of equation.'], $learner['next_time']);

        // Every goal met, no gap: "what to work on next time" has its fixed line.
        $n = new Harness([], null, null, null, null, 'ui-v3-n05');
        try {
            $n->start('N05');
            for ($i = 0; $i < 3; $i++) {
                $n->answerCorrect();
            }
            $this->assertSame('SESSION_REPORT', $n->mode());
            $learner = $n->request('GET', 'report')->body['summary']['learner'];
            $this->assertSame([], $learner['next_time']);
            $this->assertSame([], $learner['trouble']);
            $this->assertSame([], $learner['worked_on']);
            $this->assertCount(1, $learner['did_well']);
            $this->assertStringContainsString('goal met: 3 correct in a row without help', $learner['did_well'][0]);
        } finally {
            $n->cleanup();
        }
    }

    /** U2: trouble is written as observations; the route as a sentence; a gap outside the beta goes to the teacher. */
    public function testLearnerHeadingsForTheB1Journey(): void
    {
        $h = $this->h;
        $h->start('Q07');
        $h->submit($h->wrong('DIST_X_ONLY'));
        $h->submit($h->wrong('DIST_X_ONLY'));
        $p = $h->active()['params'];
        $h->submit($p['a'] . 'x+' . $p['b']);
        for ($n = 0; $n < 40 && $h->mode() !== 'SESSION_REPORT'; $n++) {
            $h->answerCorrect();
        }
        $learner = $h->request('GET', 'report')->body['summary']['learner'];
        $this->assertCount(2, $learner['did_well']);
        $this->assertContains('Twice you multiplied only the x by the number outside the parentheses.', $learner['trouble']);
        $this->assertContains('Once you multiplied only the first term inside the parentheses.', $learner['trouble']);
        $this->assertContains('We went back to “Apply the distributive property with positive factors”, then returned to “Solve equations containing parentheses”.', $learner['worked_on']);
        $this->assertSame(['Practise with your teacher: Understand grouping with parentheses (not in this tutor yet).'], $learner['next_time']);
    }

    /** U9: one self-contained HTML file: inline CSS, no scripts, no external URLs; the four headings, teacher details expanded, the date and the code. */
    public function testDownloadedReportIsSelfContained(): void
    {
        $h = $this->h;
        $h->start('Q07');
        $h->submit($h->wrong('DIST_X_ONLY'));
        $h->submit($h->wrong('DIST_X_ONLY'));
        $code = $h->request('GET', 'progress')->body['code'];
        $h->event('END_SESSION');
        $r = $h->request('GET', 'report', null, ['_query' => ['format' => 'html']]);
        $this->assertSame(200, $r->status);
        $this->assertSame('text/html; charset=utf-8', $r->headers['Content-Type']);
        $this->assertSame('attachment; filename="algebra-report-2026-10-21.html"', $r->headers['Content-Disposition']);
        $html = (string) $r->raw;
        $this->assertStringStartsWith('<!doctype html>', $html);
        $this->assertStringContainsString('<style>', $html);
        $this->assertDoesNotMatchRegularExpression('/<script|javascript:|\son\w+=|<link|<img|<iframe|<object|<embed|@import|url\(/i', $html, 'no scripts, no loaded resources');
        $this->assertDoesNotMatchRegularExpression('#https?://|//[a-z0-9.-]+\.[a-z]{2,}#i', $html, 'no external URLs');
        $this->assertStringContainsString("default-src 'none'", $html);
        foreach (['What you did well', 'Where you had trouble', 'What we worked on', 'What to work on next time', 'Details for teachers', 'Per skill, this session', 'About these results'] as $part) {
            $this->assertStringContainsString('>' . $part . '<', $html, $part);
        }
        $this->assertStringNotContainsString('<details', $html, 'teacher details are expanded');
        $this->assertStringContainsString('Date: 21 October 2026', $html);
        $this->assertStringContainsString('<span class="code">' . $code . '</span>', $html);
        $this->assertStringContainsString('No goals were met in this session yet.', $html);
        $this->assertStringContainsString('MOCK (simulated model; no live provider was used)', $html);
    }

    /** U9: text in the file is escaped, never markup. */
    public function testDownloadEscapesText(): void
    {
        $html = \AlgebraEngine\Report\ReportHtml::render([
            'provider_label' => '<b>x</b>', 'progress_code' => null,
            'summary' => [
                'learner' => ['did_well' => ['<script>alert(1)</script>'], 'trouble' => [], 'worked_on' => [], 'next_time' => [], 'earlier' => [], 'note' => 'n'],
                'teacher' => ['status' => 'S', 'topics' => [], 'skills' => [], 'sections' => []],
            ],
        ], '2026-10-21');
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $html);
    }
}
