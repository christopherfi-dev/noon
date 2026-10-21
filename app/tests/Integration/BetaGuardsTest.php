<?php
declare(strict_types=1);

namespace AlgebraEngine\Tests\Integration;

use AlgebraEngine\Support\Json;
use AlgebraEngine\Tests\Support\Harness;
use PHPUnit\Framework\TestCase;

/** Addendum §6 beta tests B5–B9. */
final class BetaGuardsTest extends TestCase
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

    /** Drive Q07 into guided repair at E06 (as in B1). */
    private function intoGuidedRepair(): void
    {
        $h = $this->h;
        $h->start('Q07');
        $h->submit($h->wrong('DIST_X_ONLY'));
        $h->submit($h->wrong('DIST_X_ONLY'));
        $h->submit($h->wrong('FIRST_TERM_ONLY'));
        $this->assertSame('GUIDED_REPAIR', $h->mode());
    }

    /** B5: the model proposes a skill that is not a declared prerequisite → rejected. */
    public function testB5NonPrerequisiteProposalRejected(): void
    {
        $h = $this->h;
        $h->start('Q07');
        $h->mock->faults[] = ['type' => 'TRANSITION', 'transition' => [
            'to_mode' => 'PREREQUISITE_CHECK', 'target_skill_id' => 'N17', 'reason' => 'Go to whole-number subtraction.', 'evidence_event_ids' => [],
        ]];
        $h->submit($h->wrong('DIST_X_ONLY'));
        $this->assertSame('DIAGNOSTIC_PROBE', $h->mode());
        $this->assertSame('Q07', $h->skill());
        $rejected = $h->events('MODEL_PROPOSAL_REJECTED');
        $this->assertCount(1, $rejected);
        $this->assertSame('N17 is not a declared prerequisite of Q07', $rejected[0]['data']['reason']);
        $this->assertSame([], $h->row()['snapshot']['return_stack']);

        // An item drafted for a non-prerequisite skill is rejected by the item gate and repaired.
        $h->mock->faults[] = ['type' => 'ITEM_PATCH', 'patch' => ['skill_id' => 'A07']];
        $h->submit($h->wrong('DIST_X_ONLY'));
        $this->assertSame(['PREREQUISITE_CHECK', 'E06'], [$h->mode(), $h->skill()]);
        $errors = array_merge(...array_map(fn ($e) => $e['data']['errors'], $h->events('MODEL_OUTPUT_REJECTED')));
        $this->assertContains('item.skill_id must be "E06"', $errors);
        $this->assertSame('E06', $h->active()['skill_id']);
    }

    /** B6: the model's proposed expected_answer disagrees with the host key → host key governs; item flagged. */
    public function testB6HostKeyGovernsAndItemFlagged(): void
    {
        $h = $this->h;
        $h->mock->faults[] = ['type' => 'WRONG_KEY', 'expected' => '999'];
        $h->start('Q07');
        $flags = $h->events('ITEM_FLAGGED');
        $this->assertCount(1, $flags);
        $this->assertSame('MODEL_KEY_DISAGREEMENT', $flags[0]['data']['flag']);
        $this->assertSame('999', $flags[0]['data']['model_expected']);
        $host = $h->active()['answer_spec']['expected'];
        $this->assertSame($host, $flags[0]['data']['host_expected']);
        $this->assertNotSame('999', $host);
        $h->submit('999');
        $this->assertSame('INCORRECT', $h->events('CHECKER_RESULT')[0]['data']['result']['verdict']);
        $this->assertSame('DIAGNOSTIC_PROBE', $h->mode());
    }

    /** B7: a hint during an independent item makes it assisted; the streak does not advance. */
    public function testB7HintMakesItemAssisted(): void
    {
        $h = $this->h;
        $h->start('Q07');
        $before = $h->view['revision'];
        $r = $h->event('REQUEST_HINT');
        $this->assertSame(200, $r->status);
        $this->assertSame($before + 1, $h->view['revision'], 'support is a committed mutation');
        $this->assertSame('ASSISTED', $h->view['active_item']['support_status']);
        $support = $h->events('SUPPORT_GIVEN');
        $this->assertCount(1, $support);
        $this->assertTrue($support[0]['data']['committed_before_display']);
        $this->assertSame($support[0]['revision'], $h->view['revision'], 'support recorded in the same commit that displays the hint');
        $h->answerCorrect();
        $this->assertSame(0, $h->view['progress']['independent_successes']);
        $result = $h->events('CHECKER_RESULT')[0]['data']['result'];
        $this->assertSame('CORRECT', $result['verdict']);
        $this->assertFalse($result['independent_eligible']);
        $this->assertSame([$support[0]['event_id']], $result['support_event_ids']);
        $this->assertSame(0, $h->row()['snapshot']['criteria'][0]['independent_correct_streak']);
    }

    /** B8: the answer-disclosure guard rejects a repair message that contains the answer. */
    public function testB8DisclosureGuardRejectsLeak(): void
    {
        $h = $this->h;
        $this->intoGuidedRepair();
        $g = $h->row()['engine']['guided'];
        $current = $h->active()['guided_step']['id'];
        $next = null;
        foreach ($g['plan']['steps'] as $s) {
            if ($s['id'] !== $current && !isset($g['done'][$s['id']])) {
                $next = $s;
                break;
            }
        }
        $this->assertNotNull($next);
        $answer = is_array($next['answer']['expected']) ? 'option ' . $next['answer']['expected'][0] : $next['answer']['expected'];
        $leak = 'Remember, the answer here is ' . str_replace('*', '', $answer) . '.';
        $h->mock->faults[] = ['type' => 'STUDENT_MESSAGE', 'text' => $leak];
        $h->answerCorrect();
        $this->assertSame('GUIDED_REPAIR', $h->mode());
        $rejected = $h->events('MODEL_OUTPUT_REJECTED');
        $this->assertCount(1, $rejected);
        $this->assertStringContainsString('expected answer', implode(' ', $rejected[0]['data']['errors']));
        foreach ($h->view['messages'] as $m) {
            $this->assertStringNotContainsString($leak, $m['text']);
        }
        $this->assertSame($next['id'], $h->active()['guided_step']['id'], 'the repaired output was accepted');
    }

    /** B8 (bounded): when every repair still leaks, the operation is held; nothing leaks and nothing is scored as failure. */
    public function testB8RepeatedLeakHolds(): void
    {
        $h = $this->h;
        $this->intoGuidedRepair();
        $g = $h->row()['engine']['guided'];
        $answers = [];
        foreach ($g['plan']['steps'] as $s) {
            if (!is_array($s['answer']['expected'])) {
                $answers[] = str_replace('*', '', $s['answer']['expected']);
            }
        }
        $leak = 'Answers: ' . implode(' and ', $answers) . '.';
        for ($i = 0; $i < 3; $i++) {
            $h->mock->faults[] = ['type' => 'STUDENT_MESSAGE', 'text' => $leak];
        }
        $h->answerCorrect();
        $this->assertSame('HOLD', $h->view['status']);
        $this->assertCount(3, $h->events('MODEL_OUTPUT_REJECTED'));
        $this->assertSame('INVALID_ITEM', $h->row()['snapshot']['issue']['type']);
        $this->assertContains('RESUME_SESSION', $h->view['allowed_actions']);
        foreach ($h->view['messages'] as $m) {
            $this->assertStringNotContainsString('Answers:', $m['text']);
        }
        $h->event('RESUME_SESSION');
        $this->assertSame('READY', $h->view['status']);
        $this->assertSame('GUIDED_REPAIR', $h->mode());
    }

    /** D1: a hint request or "I don't know" during repair yields a smaller question, never the answer. */
    public function testD1HintAndIDontKnowGiveSmallerQuestions(): void
    {
        $h = $this->h;
        $this->intoGuidedRepair();
        $first = $h->active();
        $this->assertSame("NOT_LEARNED", $h->view['allowed_actions'][3]);
        $this->assertNotContains('SKIP_ITEM', $h->view['allowed_actions'], 'no skipping guided steps');
        $h->event('REQUEST_HINT');
        $second = $h->active();
        $this->assertNotSame($first['item_id'], $second['item_id']);
        $this->assertStringStartsWith($first['guided_step']['id'] . '.', $second['guided_step']['id'], 'hint → smaller question');
        // "I don't know" at the smallest available question (no smaller question or alternate phrasing left):
        // the guided problem is set aside without showing the answer, and an independent check follows,
        // because the workflow policy has no GUIDED_REPAIR -> PREREQUISITE_CHECK edge. The implicated
        // prerequisite is carried forward for the next diagnosis.
        $this->assertSame([], $second['guided_step']['smaller']);
        $this->assertNull($second['guided_step']['alt_prompt']);
        $h->event('NOT_LEARNED');
        $this->assertSame('INDEPENDENT_CHECK', $h->mode());
        $abandoned = $h->events('GUIDED_PROBLEM_ABANDONED');
        $this->assertCount(1, $abandoned);
        $this->assertSame('N18', $abandoned[0]['data']['implicates']);
        $this->assertSame('N18', $h->row()['engine']['guided_last_branch']);
        $closed = array_column(array_column($h->events('ITEM_CLOSED'), 'data'), 'reason');
        $this->assertContains('STUCK_HINT', $closed);
        $this->assertContains('I_DONT_KNOW', $closed);
        foreach ([$first, $second] as $step) {
            $answer = (string) (is_array($step['answer_spec']['expected']) ? '' : $step['answer_spec']['expected']);
            foreach ($h->view['messages'] as $m) {
                $this->assertSame([], \AlgebraEngine\Provider\DisclosureGuard::violations($m['text'], [['answer' => $step['answer_spec'], 'variables' => ['x']]]), $answer);
            }
        }
        $this->assertSame([], array_filter($h->events('CHECKER_RESULT'), fn ($e) => $e['data']['result']['verdict'] === 'INCORRECT' && $e['data']['role'] === 'GUIDED'), 'stuck signals are not scored as wrong');
    }

    /** B9: prompt assembly contains no anchor prompt and no example content (F2). */
    public function testB9PromptsCarryNoAnchorOrExampleContent(): void
    {
        $h = $this->h;
        $this->intoGuidedRepair();
        $h->completeGuidedRepair();
        $h->answerCorrect();
        $h->event('REQUEST_HINT');
        $this->assertNotEmpty($h->mock->log);

        $forbidden = [];
        foreach ($h->app->curriculum->ids() as $id) {
            $forbidden[] = $h->app->curriculum->skill($id)['assessment']['anchor_item']['prompt'];
            $forbidden[] = $h->app->curriculum->skill($id)['assessment']['anchor_item']['item_id'];
        }
        $forbidden = array_merge($forbidden, self::exampleStrings());
        $this->assertGreaterThan(220, count($forbidden));

        foreach ($h->mock->log as $call) {
            $text = implode("\n", array_column($call['messages'], 'content'));
            foreach ($forbidden as $f) {
                $this->assertStringNotContainsString($f, $text, 'prompt contains forbidden content: ' . $f);
            }
            if ($call['json']) {
                $context = Json::decode(substr(end($call['messages'])['content'], 5), 64);
                foreach ($context['TRUSTED_CURRICULUM']['records'] as $rec) {
                    $this->assertArrayNotHasKey('anchor_item', $rec['assessment']);
                }
                self::assertPlaceholdersOnly($context['OUTPUT_CONTRACT']['envelope_skeleton']);
            }
        }
    }

    /**
     * Strings from examples/: every example identifier and every prose or
     * math string of 12+ characters. Strings that are also canonical
     * curriculum values (for example a skill statement reused as a title in
     * an example view) come from the curriculum, not from the examples, and
     * are excluded.
     */
    public static function exampleStrings(): array
    {
        $canonical = (string) file_get_contents(ALGEBRA_REPO_ROOT . '/curriculum/algebra-map.json');
        $out = [];
        $walk = function ($v) use (&$walk, &$out) {
            if (is_array($v)) {
                array_walk($v, $walk);
            } elseif (is_string($v) && (str_starts_with($v, 'example-') || (mb_strlen($v) >= 12 && str_contains($v, ' ')))) {
                $out[] = $v;
            }
        };
        foreach (glob(ALGEBRA_REPO_ROOT . '/examples/*.json') as $f) {
            $walk(json_decode((string) file_get_contents($f), true));
        }
        foreach (explode("\n", (string) file_get_contents(ALGEBRA_REPO_ROOT . '/examples/EXPECTED_WALKTHROUGH.md')) as $line) {
            $line = trim($line, " #0123456789.\t");
            if (mb_strlen($line) >= 24) {
                $out[] = $line;
            }
        }
        return array_values(array_unique(array_filter($out, fn ($s) => !str_contains($canonical, json_encode($s, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)))));
    }

    private static function assertPlaceholdersOnly(mixed $v): void
    {
        if (is_array($v)) {
            foreach ($v as $x) {
                self::assertPlaceholdersOnly($x);
            }
            return;
        }
        if ($v === []) {
            return;
        }
        self::assertContains($v, ['<string>', '<integer>', '<number>', '<boolean>', null]);
    }
}
