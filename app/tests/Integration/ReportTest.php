<?php
declare(strict_types=1);

namespace AlgebraEngine\Tests\Integration;

use AlgebraEngine\Curriculum\CurriculumRepository;
use AlgebraEngine\Math\Rational;
use AlgebraEngine\Report\ReportHtml;
use AlgebraEngine\Report\SummaryView;
use AlgebraEngine\Support\LearnerText;
use AlgebraEngine\Tests\Support\Harness;
use PHPUnit\Framework\TestCase;

/**
 * Round 6 §2.5: code writes the report from the gradebook, keeping the UI v3
 * report and its rules (headings, fixed empty lines, plain words, no codes,
 * titles for learners, title (ID) for teachers, download, escaping) and
 * adding the §5.2 audit list.
 */
final class ReportTest extends TestCase
{
    private const LEVEL_TERMS = '/\b(declared prerequisites?|descents?|descended|implicated|branch(?:es)?|transfer variants?|retention claims?|faded support|coverage)\b/i';
    private const CODES = '/\b[A-Z]{2,}(?:_[A-Z0-9]+)+\b|\b(?:all|any)_of\b|\bPOLICY-[A-Z0-9-]+\b/';

    /** The step-back journey: a distribution-to-x-only answer, E06 practice and checks, then Q07 to the goal. */
    private function journey(): Harness
    {
        $h = new Harness([], null, null, null, null, 'report-journey');
        $h->start('Q07');
        $p = $h->open()['params'];
        $h->send((string) Rational::of($p['c'] - $p['b'], $p['a']));
        $h->stuck('HINT');
        for ($i = 0; $i < 20 && !$h->view['wrap_up']; $i++) {
            $h->answerCorrectly();
        }
        $this->assertTrue($h->view['wrap_up']);
        $h->event('END_SESSION', [], null, null, false);
        return $h;
    }

    public function testEmptyHeadingsUseTheirFixedLines(): void
    {
        $this->assertSame([
            'did_well' => 'No goals were met in this session yet.',
            'trouble' => 'No trouble spots were recorded in this session.',
            'worked_on' => 'This session had no practice with help.',
            'next_time' => 'You met every goal available in this tutor so far. Ask your teacher what comes next.',
        ], SummaryView::EMPTY);
        $this->assertSame(['What you did well', 'Where you had trouble', 'What we worked on', 'What to work on next time'], array_values(SummaryView::HEADINGS));
        $h = new Harness();
        $h->start('Q07');
        $h->event('END_SESSION', [], null, null, false);
        $learner = $h->request('GET', 'report')->body['summary']['learner'];
        $this->assertSame([[], [], []], [$learner['did_well'], $learner['trouble'], $learner['worked_on']], 'nothing is invented to fill a heading');
        $this->assertSame(['Solve equations containing parentheses: aim for 3 correct without help (one slip may pause the count), including a solve, a step choice and a new kind of equation.'], $learner['next_time']);
    }

    public function testLearnerHeadingsForTheStepBackJourney(): void
    {
        $h = $this->journey();
        $learner = $h->request('GET', 'report')->body['summary']['learner'];
        $this->assertCount(2, $learner['did_well']);
        foreach ($learner['did_well'] as $line) {
            $this->assertStringContainsString('goal met: 3 correct without help (one slip may pause the count)', $line);
        }
        $this->assertContains('Once you multiplied only the first term inside the parentheses.', $learner['trouble'], 'an observation, in plain words (round 7 §8.3: one description on Q07 and E06)');
        $this->assertContains('Solve equations containing parentheses: 1 answer was not right yet.', $learner['trouble']);
        $this->assertContains('Apply the distributive property with positive factors: you used help.', $learner['trouble'], 'the hint was on the step-back practice');
        $this->assertContains('We went back to “Apply the distributive property with positive factors”, then returned to “Solve equations containing parentheses”.', $learner['worked_on'], 'the route as a sentence');
        $this->assertSame([], $learner['next_time'], 'every goal met: the fixed line shows');
    }

    public function testOutsideSkillIsListedOnlyWhileADependentGoalIsUnmet(): void
    {
        $h = new Harness();
        $h->start('E06');
        TutorLoopTest::pose($h, 'check', 'E06', 'expand', '9(x + 8)', '9x + 72', '', 'Here is a check question.');
        $h->send('9x + 8'); // the factor reached only the x: the recipe points to A07, outside this tutor
        $h->event('END_SESSION', [], null, null, false);
        $next = $h->request('GET', 'report')->body['summary']['learner']['next_time'];
        $this->assertContains('Practise with your teacher: Understand grouping with parentheses (not in this tutor yet).', $next, json_encode($next));
    }

    public function testSummaryLevelsUsePlainWordsAndNoCodes(): void
    {
        $curriculum = CurriculumRepository::fromFile(ALGEBRA_APP_ROOT . '/private/resources/curriculum/algebra-map.json');
        $report = $this->journey()->request('GET', 'report')->body;
        $learner = $report['summary']['learner'];
        $teacher = $report['summary']['teacher'];
        $this->assertSame(['did_well', 'trouble', 'worked_on', 'next_time', 'headings', 'empty', 'note', 'earlier'], array_keys($learner));
        foreach (['learner' => $learner, 'teacher' => $teacher] as $level => $data) {
            $this->assertSame([], self::duplicates($data), "$level: a list repeats a line");
            foreach (self::strings($data) as [$path, $text]) {
                $where = "$level$path: $text";
                $this->assertSame([], LearnerText::internalTerms($text), $where);
                $this->assertDoesNotMatchRegularExpression(self::LEVEL_TERMS, $text, $where);
                $this->assertDoesNotMatchRegularExpression(self::CODES, $text, $where);
                $this->assertDoesNotMatchRegularExpression('/master/i', $text, $where);
                if ($level === 'learner') {
                    $this->assertDoesNotMatchRegularExpression('/\b[A-Z]\d{2}\b/', $text, "$where: the learner level has no skill IDs");
                } else {
                    preg_match_all('/ \(([A-Z]\d{2})\)/', $text, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
                    foreach ($m as [$whole, $id]) {
                        $this->assertStringEndsWith($curriculum->title($id[0]), substr($text, 0, $whole[1]), $where);
                    }
                    $this->assertSame(count($m), preg_match_all('/\b[A-Z]\d{2}\b/', $text), "$where: every skill ID is in brackets after its title");
                }
            }
        }
        foreach ($teacher['skills'] as $skill) {
            foreach ($skill['observed'] as $o) {
                $this->assertStringContainsString('not a confirmed cause', $o);
            }
        }
        $about = end($teacher['sections'])['lines'][0];
        $this->assertStringContainsString('Simulated tutor (mock mode)', $about, 'mock sessions are labelled as mock');
        $this->assertStringContainsString('not a long-term learning claim', $about);
        $this->assertSame('MOCK (simulated tutor; no live provider was used)', $report['provider_label']);
    }

    public function testNoAiTextInTheReportAndTheAuditList(): void
    {
        $h = new Harness();
        $h->start('Q06');
        $h->mock->queue('ENVELOPE', ['set' => ['message_type' => 'QUESTION', 'student_message' => 'Good. Next, try this one and remember the order.', 'item' => [
            'purpose' => 'check', 'skill_id' => 'Q06', 'source' => 'spec', 'recipe_ref' => null,
            'spec' => ['kind' => 'linear_equation', 'expression' => '3x + 4 = 19'], 'prompt' => '', 'stated_answer' => '5']]]);
        $h->answerCorrectly();
        $this->assertSame('check', $h->view['open_problem']['purpose']);
        $audit = $h->eventsOf('CHECK_CARD_AUDIT');
        $this->assertSame(['next', 'try', 'remember'], $audit[0]['data']['phrases']);
        $this->assertStringContainsString('Next, try this one', $audit[0]['data']['text'], 'the full text stays in the ledger for review');
        $h->event('END_SESSION', [], null, null, false);
        $report = $h->request('GET', 'report')->body;
        $section = array_values(array_filter($report['summary']['teacher']['sections'], fn ($s) => $s['heading'] === 'Before check questions (audit)'))[0];
        $this->assertSame(['Before the check question “Solve 3x + 4 = 19 for x.”, the tutor\'s text had phrases directed at it: next, try, remember. The answer to it is held. (Audit list only; the tutor\'s own text is not part of this report.)'], $section['lines']);
        $json = json_encode($report);
        foreach (array_column(array_filter($h->lines(), fn ($l) => $l['role'] === 'tutor' && $l['kind'] === 'message'), 'text') as $tutorText) {
            $this->assertStringNotContainsString(json_encode($tutorText), $json, 'no AI-written text appears in the report');
        }
    }

    public function testDownloadedReportIsSelfContained(): void
    {
        $h = new Harness();
        $h->start('Q07');
        $h->send($h->wrong());
        $code = $h->request('GET', 'progress')->body['code'];
        $h->event('END_SESSION', [], null, null, false);
        $r = $h->request('GET', 'report', null, ['_query' => ['format' => 'html']]);
        $this->assertSame(200, $r->status);
        $this->assertSame('text/html; charset=utf-8', $r->headers['Content-Type']);
        $this->assertSame('attachment; filename="algebra-report-2026-10-21.html"', $r->headers['Content-Disposition']);
        $html = (string) $r->raw;
        $this->assertStringStartsWith('<!doctype html>', $html);
        $this->assertDoesNotMatchRegularExpression('/<script|javascript:|\son\w+=|<link|<img|<iframe|<object|<embed|@import|url\(/i', $html);
        $this->assertDoesNotMatchRegularExpression('#https?://|//[a-z0-9.-]+\.[a-z]{2,}#i', $html);
        foreach (['What you did well', 'Where you had trouble', 'What we worked on', 'What to work on next time', 'Details for teachers', 'About these results'] as $part) {
            $this->assertStringContainsString('>' . $part . '<', $html, $part);
        }
        $this->assertStringNotContainsString('<details', $html);
        $this->assertStringContainsString('<span class="code">' . $code . '</span>', $html);
        $this->assertStringContainsString('No goals were met in this session yet.', $html);
        $this->assertStringContainsString('MOCK (simulated tutor; no live provider was used)', $html);
    }

    public function testDownloadEscapesText(): void
    {
        $html = ReportHtml::render([
            'provider_label' => '<b>x</b>', 'progress_code' => null,
            'summary' => [
                'learner' => ['did_well' => ['<script>alert(1)</script>'], 'trouble' => [], 'worked_on' => [], 'next_time' => [], 'earlier' => [], 'note' => 'n'],
                'teacher' => ['status' => 'S', 'topics' => [], 'skills' => [], 'sections' => []],
            ],
        ], '2026-10-21');
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    /** EVID-03: three correct checks on a skill that also needs conceptual evidence: the goal covers the numeric part only and the rest stays open. */
    public function testEvid03NumericOnlySkill(): void
    {
        $h = new Harness();
        $h->start('Q06');
        for ($i = 0; $i < 8 && !$h->goal('Q06')['met']; $i++) {
            $h->answerCorrectly();
        }
        $this->assertTrue($h->goal('Q06')['met']);
        $body = $h->request('GET', 'report')->body;
        $this->assertSame(['numeric_component'], array_column($body['report']['immediate_criteria'], 'component_id'));
        $skill = $body['summary']['teacher']['skills'][0];
        $this->assertSame('the numeric part only', $skill['checked']);
        $open = self::section($body, 'Still open or not checked');
        $this->assertContains('Solve two-step equations (Q06): the goal covers the numeric part only; explaining a method or rejecting a wrong one was not checked.', $open, json_encode($open));
    }

    /** EVID-04: three varied successes in one session are an immediate, in-session goal only; no mastery and no retention claim. */
    public function testEvid04ImmediateVersusRetention(): void
    {
        $body = $this->journey()->request('GET', 'report')->body;
        $record = $body['report'];
        $this->assertSame(['E06', 'Q07'], array_column($record['immediate_criteria'], 'skill_id'));
        $this->assertSame(['three_variant_criterion', 'three_variant_criterion'], array_column($record['immediate_criteria'], 'component_id'));
        foreach ($record['immediate_criteria'] as $c) {
            $this->assertStringContainsString('no long-term learning claim', $c['statement']);
            $this->assertCount(3, $c['event_ids'], 'bound to the three check answers');
        }
        $this->assertSame([], $record['retention_evidence']);
        $this->assertDoesNotMatchRegularExpression('/master|retain|retention (?:met|shown)/i', json_encode($body['summary']));
        $this->assertContains('There is no later review check yet, so nothing here shows what is remembered over time.', self::section($body, 'Still open or not checked'));
    }

    /** EVID-06: after one Q07 journey, no untested skill carries a pass or fail label, and the next evidence is scoped to the topic. */
    public function testEvid06UntestedSkillsCarryNoLabel(): void
    {
        $h = $this->journey();
        $body = $h->request('GET', 'report')->body;
        $tested = array_values(array_unique(array_column($h->attempts(), 'skill_id')));
        sort($tested);
        $named = array_map(fn ($s) => substr($s['name'], -4, 3), $body['summary']['teacher']['skills']);
        sort($named);
        $this->assertSame($tested, $named, 'only skills with attempts are listed');
        $this->assertContains('Skills not listed above were not tested in this session and carry no pass or fail label.', self::section($body, 'Still open or not checked'));
        $this->assertSame(['Q07'], $body['report']['targets_attempted']);
        foreach ($body['report']['next_evidence_needed'] as $line) {
            $this->assertStringContainsString('Q07', $line, 'next evidence is scoped to the topic');
        }
    }

    /** Stored wording that reaches the teacher level is rewritten in plain words. */
    public function testPlainWordsForStoredText(): void
    {
        foreach ([
            'Exact rational comparison with the host-derived key; no tolerance; reasoning not assessed.' => 'Exact rational comparison with the answer the app computed; no tolerance; reasoning not assessed.',
            'The provider output failed host acceptance after 2 repair attempts.' => 'The provider output failed app acceptance after 2 repair attempts.',
        ] as $stored => $plain) {
            $this->assertSame($plain, SummaryView::plain($stored));
            $this->assertSame([], LearnerText::internalTerms(SummaryView::plain($stored)));
        }
    }

    /** @return list<string> */
    private static function section(array $body, string $heading): array
    {
        foreach ($body['summary']['teacher']['sections'] as $s) {
            if ($s['heading'] === $heading) {
                return $s['lines'];
            }
        }
        self::fail('no section ' . $heading);
    }

    /** @return list<array{0:string,1:string}> */
    private static function strings(mixed $value, string $path = ''): array
    {
        if (is_string($value)) {
            return [[$path, $value]];
        }
        $out = [];
        foreach (is_array($value) ? $value : [] as $k => $v) {
            $out = array_merge($out, self::strings($v, $path . '/' . $k));
        }
        return $out;
    }

    /** @return list<string> */
    private static function duplicates(mixed $value, string $path = ''): array
    {
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        if ($value !== [] && array_keys($value) === range(0, count($value) - 1) && is_string($value[0]) && count($value) !== count(array_unique($value))) {
            $out[] = $path;
        }
        foreach ($value as $k => $v) {
            $out = array_merge($out, self::duplicates($v, $path . '/' . $k));
        }
        return $out;
    }
}
