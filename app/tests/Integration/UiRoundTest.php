<?php
declare(strict_types=1);

namespace AlgebraEngine\Tests\Integration;

use AlgebraEngine\Support\LearnerText;
use AlgebraEngine\Support\ProblemText;
use AlgebraEngine\Tests\Support\Harness;
use PHPUnit\Framework\TestCase;

/** Conductor round UI: what the student sees (U1, U4, U6, U7, U8). The summary (U2) is in SummaryWordingTest. */
final class UiRoundTest extends TestCase
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

    /** @return list<string> */
    private static function numbers(string $text): array
    {
        preg_match_all('/\d+/', $text, $m);
        return array_values(array_unique($m[0]));
    }

    /** @return list<string> */
    private function labelLines(): array
    {
        return explode("\n", $this->h->view['progress']['label']);
    }

    /** Into B1's first guided problem; returns the missed E06 problem and the learner's answer to it. */
    private function intoFirstGuidedProblem(): array
    {
        $h = $this->h;
        $h->start('Q07');
        $h->submit($h->wrong('DIST_X_ONLY'));
        $h->submit($h->wrong('DIST_X_ONLY'));
        $this->assertSame('PREREQUISITE_CHECK', $h->mode());
        $missed = $h->view['active_item']['prompt'];
        $p = $h->active()['params'];
        $answer = $p['a'] . 'x+' . $p['b'];
        $h->submit($answer);
        $this->assertSame('GUIDED_REPAIR', $h->mode());
        return [$missed, $answer, $p];
    }

    /** U1 (conductor test): the feedback before the first guided problem names the missed problem and the learner's answer. */
    public function testU1FeedbackBeforeFirstGuidedProblemNamesTheMissedProblem(): void
    {
        [$missed, $answer, $p] = $this->intoFirstGuidedProblem();
        $messages = $this->h->view['messages'];
        $line = 'On ' . $p['a'] . '(x + ' . $p['b'] . ') you answered ' . $answer . '.';
        $this->assertSame(['FEEDBACK', 'Not yet.'], [$messages[0]['kind'], $messages[0]['text']]);
        $this->assertSame(['INFORMATION', $line], [$messages[1]['kind'], $messages[1]['text']], 'the problem and the answer come first');
        $this->assertSame($line, ProblemText::answerLine($missed, 'expression', $answer));
        $opening = implode(' ', array_column(array_slice($messages, 2), 'text'));
        $this->assertStringContainsString('Your answer multiplied only the x by ' . $p['a'], $opening, 'the observation follows the line that names its problem');
        // No number in the feedback comes only from a problem the learner can no longer see.
        $visible = array_merge(self::numbers($line), self::numbers($this->h->view['active_item']['prompt']));
        $this->assertSame([], array_values(array_diff(self::numbers($opening), $visible)), 'numbers in the opening: ' . $opening);
    }

    /** U1: a guided step that does not repeat its problem is shown under it; the stored prompt is unchanged. */
    public function testU1GuidedStepsKeepTheirProblemOnScreen(): void
    {
        $this->intoFirstGuidedProblem();
        $h = $this->h;
        $seen = 0;
        for ($i = 0; $i < 40 && $h->mode() === 'GUIDED_REPAIR'; $i++) {
            $active = $h->active();
            $problem = $active['objects']['plan_problem'];
            $shown = $h->view['active_item']['prompt'];
            $this->assertStringContainsString($problem, $shown, 'step ' . $active['guided_step']['id']);
            if (!str_contains($active['prompt'], $problem)) {
                $this->assertSame('Problem: ' . $problem . "\n" . $active['prompt'], $shown);
                $seen++;
            } else {
                $this->assertSame($active['prompt'], $shown);
            }
            $i % 3 === 1 ? $h->submit($h->plainWrong()) : $h->answerCorrect();
        }
        $this->assertGreaterThan(0, $seen, 'at least one step needed its problem line');
    }

    /** U7: the goal line counts correct answers in a row without help and names the kinds still needed; it empties on a reset. */
    public function testU7GoalLineAndReset(): void
    {
        $h = $this->h;
        $h->start('Q07');
        $this->assertSame(['', '0 of 3 in a row without help', 'Still needed: a solve, a step choice and a new kind of equation.'], $this->labelLines());
        $this->assertSame([0, 3], [$h->view['progress']['independent_successes'], $h->view['progress']['criterion_required']]);
        $h->answerCorrect();
        $this->assertSame(['', '1 of 3 in a row without help', 'Still needed: a step choice and a new kind of equation.'], $this->labelLines());
        $h->answerCorrect();
        $this->assertSame(['', '2 of 3 in a row without help', 'Still needed: a new kind of equation.'], $this->labelLines());
        $this->assertSame(2, $h->view['progress']['independent_successes']);
        $h->submit($h->plainWrong());
        $this->assertSame('DIAGNOSTIC_PROBE', $h->mode());
        $this->assertSame(['', 'This question helps choose what to practise. It does not count toward the goal.'], $this->labelLines(), 'a probe has no goal line');
        $this->assertSame(0, $h->view['progress']['independent_successes'], 'the streak reset empties the dots');
        $h->answerCorrect();
        $this->assertSame('0 of 3 in a row without help', $this->labelLines()[1]);
        // A hint on a counted item: the count starts again after the answer (round 5.1, P2).
        $h->event('REQUEST_HINT');
        $this->assertContains('You had help on this one, so the count starts again after your answer.', $this->labelLines());
    }

    /** U7: a building block names itself and its own kinds; a numeric-only skill has no "still needed" line. */
    public function testU7BuildingBlockAndNumericOnlySkill(): void
    {
        $h = $this->h;
        $h->start('Q07');
        $h->submit($h->wrong('DIST_X_ONLY'));
        $h->submit($h->wrong('DIST_X_ONLY'));
        $this->assertSame([
            'Building block: Apply the distributive property with positive factors.',
            '0 of 3 in a row without help',
            'Still needed: an expansion, a choice question and a new kind of expression.',
        ], $this->labelLines());

        $n = new Harness([], null, null, null, null, 'ui-numeric');
        try {
            $n->start('N05');
            $this->assertSame("\n0 of 3 in a row without help", $n->view['progress']['label']);
        } finally {
            $n->cleanup();
        }
    }

    /** U6: the guided-work notice is the tag on the item only; the header label does not repeat it. */
    public function testU6GuidedLabelCarriesNoNotice(): void
    {
        $this->intoFirstGuidedProblem();
        $this->assertSame('Building block: Apply the distributive property with positive factors.', $this->h->view['progress']['label']);
        $this->assertSame('ASSISTED', $this->h->view['active_item']['support_status'], 'the item still carries the tag');
    }

    /** U8: "Not yet." replaces "That is not correct." everywhere; correct answers say "Correct." in every role. */
    public function testU8ResultWords(): void
    {
        $h = $this->h;
        $feedback = [];
        $collect = function () use ($h, &$feedback): void {
            foreach ($h->view['messages'] as $m) {
                if ($m['kind'] === 'FEEDBACK') {
                    $feedback[] = $h->mode() . ': ' . $m['text'];
                }
            }
        };
        $h->start('Q07');
        foreach (['DIST_X_ONLY', 'DIST_X_ONLY'] as $code) {
            $h->submit($h->wrong($code));
            $collect();
        }
        $p = $h->active()['params'];
        $h->submit($p['a'] . 'x+' . $p['b']);
        $collect();
        $h->submit($h->plainWrong());                 // a guided step, wrong
        $collect();
        for ($i = 0; $i < 40 && $h->mode() !== 'SESSION_REPORT'; $i++) {
            $h->answerCorrect();
            $collect();
        }
        $words = array_values(array_unique(array_map(fn ($f) => substr($f, strpos($f, ': ') + 2), $feedback)));
        sort($words);
        $this->assertSame(['Correct.', 'Not yet.'], $words);
        $this->assertContains('GUIDED_REPAIR: Not yet.', $feedback);
        $this->assertTrue((bool) array_filter($feedback, fn ($f) => str_starts_with($f, 'GUIDED_REPAIR: Correct.') || str_starts_with($f, 'INDEPENDENT_CHECK: Correct.')));
        $this->assertContains('DIAGNOSTIC_PROBE: Not yet.', $feedback);
        $source = (string) file_get_contents(ALGEBRA_APP_ROOT . '/private/src/Workflow/Engine.php');
        $this->assertStringNotContainsString('That is not correct.', $source);
        $this->assertStringNotContainsString('Yes, that is right.', $source);
    }

    /** U4: the working box is gone; the notices no longer mention working; the API still accepts an empty working field. */
    public function testU4WorkingRemoved(): void
    {
        $h = $this->h;
        $boot = $h->bootstrap();
        foreach ($boot->body['notices'] as $notice) {
            $this->assertStringNotContainsStringIgnoringCase('working', $notice);
        }
        $html = (string) file_get_contents(ALGEBRA_APP_ROOT . '/public/index.html');
        $js = (string) file_get_contents(ALGEBRA_APP_ROOT . '/public/assets/app.js');
        $this->assertStringNotContainsStringIgnoringCase('working are', $html);
        $this->assertStringNotContainsString('textarea', $js);
        $this->assertStringNotContainsString('working-input', $js);
        $h->start('Q07');
        $this->assertSame(200, $h->event('SUBMIT_RESPONSE', ['text' => $h->correct(), 'working' => ''])->status, 'an empty working field is accepted');
        $this->assertSame(200, $h->event('SUBMIT_RESPONSE', ['text' => $h->correct()])->status, 'no working field is accepted');
        $this->assertSame([], LearnerText::internalTerms($h->view['progress']['label']));
    }
}
