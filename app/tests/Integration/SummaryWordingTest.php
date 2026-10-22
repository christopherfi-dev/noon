<?php
declare(strict_types=1);

namespace AlgebraEngine\Tests\Integration;

use AlgebraEngine\Curriculum\CurriculumRepository;
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
        foreach ($this->summaries() as $name => $s) {
            $this->assertSame([], self::hits($s), $name);
        }
    }

    /** @return array<string,array> the B1 and B3 summaries */
    private function summaries(): array
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
        return $summaries;
    }

    /** Round UI (U2): terms neither summary level may show, and raw codes. */
    private const LEVEL_TERMS = '/\\b(declared prerequisites?|descents?|descended|implicated|branch(?:es)?|transfer variants?|retention claims?|faded support|coverage)\\b/i';
    private const CODES = '/\\b[A-Z]{2,}(?:_[A-Z0-9]+)+\\b|\\b(?:all|any)_of\\b|\\bPOLICY-[A-Z0-9-]+\\b/';

    /** @return list<array{0:string,1:string}> [path, text] for every string in a summary level */
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

    /** @return list<string> lists that repeat a line */
    private static function duplicates(mixed $value, string $path = ''): array
    {
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        if ($value !== [] && array_keys($value) === range(0, count($value) - 1) && is_string($value[0])) {
            if (count($value) !== count(array_unique($value))) {
                $out[] = $path;
            }
        }
        foreach ($value as $k => $v) {
            $out = array_merge($out, self::duplicates($v, $path . '/' . $k));
        }
        return $out;
    }

    /**
     * Round UI (U2): the learner level (titles only) and the teacher level (titles with IDs in
     * brackets) use plain words, show no raw codes, repeat no line and keep every truth rule.
     */
    public function testSummaryLevelsUsePlainWordsAndNoCodes(): void
    {
        $curriculum = CurriculumRepository::fromFile(ALGEBRA_APP_ROOT . '/private/resources/curriculum/algebra-map.json');
        foreach ($this->summaries() as $name => $s) {
            $this->assertArrayHasKey('summary', $s, $name);
            $learner = $s['summary']['learner'];
            $teacher = $s['summary']['teacher'];
            $this->assertSame(['worked_on', 'goals_met', 'hard', 'next', 'note'], array_keys($learner), $name);
            foreach (['learner' => $learner, 'teacher' => $teacher] as $level => $data) {
                $this->assertSame([], self::duplicates($data), "$name $level: a list repeats a line");
                foreach (self::strings($data) as [$path, $text]) {
                    $where = "$name $level$path: $text";
                    $this->assertSame([], LearnerText::internalTerms($text), $where);
                    $this->assertDoesNotMatchRegularExpression(self::LEVEL_TERMS, $text, $where);
                    $this->assertDoesNotMatchRegularExpression(self::CODES, $text, $where);
                    $this->assertDoesNotMatchRegularExpression('/master/i', $text, $where);
                    if ($level === 'learner') {
                        $this->assertDoesNotMatchRegularExpression('/\\b[A-Z]\\d{2}\\b/', $text, "$where: the learner level has no skill IDs");
                    } else {
                        // A skill ID appears only in brackets right after its title.
                        preg_match_all('/ \\(([A-Z]\\d{2})\\)/', $text, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
                        foreach ($m as [$whole, $id]) {
                            $this->assertStringEndsWith($curriculum->title($id[0]), substr($text, 0, $whole[1]), $where);
                        }
                        $this->assertSame(count($m), preg_match_all('/\\b[A-Z]\\d{2}\\b/', $text), "$where: every skill ID is in brackets after its title");
                    }
                }
            }
            foreach ($teacher['skills'] as $skill) {
                foreach ($skill['observed'] as $o) {
                    $this->assertStringContainsString('not a confirmed cause', $o, $name);
                }
            }
            $about = end($teacher['sections'])['lines'][0];
            $this->assertStringContainsString('Simulated tutor (mock mode)', $about, 'mock sessions are labelled as mock');
            $this->assertStringContainsString('not a long-term learning claim', $about);
            $this->assertStringContainsString('These results describe this session only', $learner['note']);
        }
        $b1 = $this->summaries()['B1']['summary'];
        $this->assertCount(2, $b1['learner']['goals_met']);
        foreach ($b1['learner']['goals_met'] as $g) {
            $this->assertStringContainsString('goal met: 3 correct in a row without help', $g);
        }
        $this->assertSame(['Apply the distributive property with positive factors: you worked through it step by step with help.'], $b1['learner']['hard']);
        $q07 = $b1['teacher']['skills'][0];
        $this->assertSame('Solve equations containing parentheses (Q07)', $q07['name']);
        $this->assertCount(1, $q07['observed'], 'the same observation is listed once');
        $this->assertStringEndsWith('Seen 2 times.', $q07['observed'][0]);
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
