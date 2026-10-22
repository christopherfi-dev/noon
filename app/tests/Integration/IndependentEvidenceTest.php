<?php
declare(strict_types=1);

namespace AlgebraEngine\Tests\Integration;

use AlgebraEngine\Workflow\Engine;
use AlgebraEngine\Tests\Support\Harness;
use PHPUnit\Framework\TestCase;

/**
 * Round 5, R5-A: no help on independent items. B14 is also checked by the
 * harness after every request of every integration test; these tests add
 * the discard record, journey coverage and the criterion message (B14b).
 */
final class IndependentEvidenceTest extends TestCase
{
    private const CUE = 'Work out the sizes first, then settle the sign.';

    /** Criterion messages that say the work was done without help. */
    private static function criterionMessages(array $messages): array
    {
        return array_values(array_filter(array_column($messages, 'text'), fn ($t) => str_contains($t, 'correctly in a row, without help')));
    }

    /** B14: across B1 and B3 journeys, every independent item is shown with the host line only; the model's cue is discarded. */
    public function testB14IndependentItemsShowHostLineOnly(): void
    {
        $checked = 0;
        for ($i = 0; $i < 12; $i++) {
            $h = new Harness([], null, null, null, null, "b14-$i");
            try {
                $h->start('Q07');
                $wrong = $i % 2 ? ['DIST_X_ONLY', 'DIST_X_ONLY', 'FIRST_TERM_ONLY'] : ['SIGN_ERROR_B', 'SIGN_ERROR_B', 'MULTIPLY_INSTEAD_OF_DIVIDE', 'QUOTIENT_SIGN', 'NO_SIGNATURE'];
                $seen = [];
                $record = function () use ($h, &$seen): void {
                    $a = $h->row()['engine']['active'];
                    if ($a !== null && $a['role'] !== 'GUIDED') {
                        $seen[] = array_column($h->view['messages'], 'text');
                    }
                };
                $record();
                foreach ($wrong as $code) {
                    $h->submit($h->wrong($code));
                    $record();
                }
                for ($n = 0; $n < 40 && $h->view['status'] === 'READY' && $h->mode() !== 'SESSION_REPORT'; $n++) {
                    $h->answerCorrect();
                    $record();
                }
                $this->assertSame('SESSION_REPORT', $h->mode());
                $this->assertNotEmpty($seen);
                foreach ($seen as $texts) {
                    $this->assertContains(Engine::INDEPENDENT_LEAD, $texts);
                    $this->assertNotContains(self::CUE, $texts);
                }
                $presented = array_filter($h->events('ITEM_PRESENTED'), fn ($e) => $e['data']['role'] !== 'GUIDED');
                foreach ($presented as $e) {
                    $this->assertFalse($e['data']['model_text_shown']);
                }
                $this->assertCount(count($presented), $h->events('MODEL_TEXT_DISCARDED'), 'the mock wrote a cue with every independent item; each was discarded');
                $checked += $h->b14Checked;
            } finally {
                $h->cleanup();
            }
        }
        $this->assertGreaterThan(100, $checked);
    }

    /** B14: discarded model text is never stored as learner-visible: not in messages, history, events or the public view. */
    public function testB14DiscardedTextIsNotStored(): void
    {
        $h = new Harness();
        try {
            $h->start('N06');
            $cue = 'Divide the sizes, then a negative divided by a positive is negative.';
            $h->mock->faults[] = ['type' => 'STUDENT_MESSAGE', 'text' => $cue];
            $h->answerCorrect();
            $this->assertSame('TARGET_ASSESSMENT', $h->mode());
            $this->assertSame(['Correct.', Engine::INDEPENDENT_LEAD], array_column($h->view['messages'], 'text'));
            $row = $h->row();
            $this->assertStringNotContainsString('Divide the sizes', json_encode($row['engine']) . json_encode($row['snapshot']) . json_encode($h->events()));
            $discarded = $h->events('MODEL_TEXT_DISCARDED');
            $this->assertSame(mb_strlen($cue), $discarded[count($discarded) - 1]['data']['characters']);
            $this->assertSame([], $h->events('MODEL_OUTPUT_REJECTED'), 'discarded text is not checked, so it causes no repair');
        } finally {
            $h->cleanup();
        }
    }

    /**
     * B14b: a criterion message saying "without help" is produced only when every counted item was
     * independent, had no hint and passed B14. Round 5.1 owner rule: an answer given after a hint resets
     * the run, so a hint on the third answer means the criterion is met on answers 4, 5 and 6.
     */
    public function testB14bHintMeansNoWithoutHelpMessage(): void
    {
        $h = new Harness();
        try {
            $h->start('N06');
            $answered = [];
            $answer = function () use ($h, &$answered) {
                $answered[] = $h->active()['item_id'];
                return $h->answerCorrect();
            };
            $answer();
            $answer();
            $this->assertSame(2, $h->view['progress']['independent_successes']);
            $hinted = $h->active()['item_id'];
            $this->assertSame(200, $h->event('REQUEST_HINT')->status);
            $this->assertSame('ASSISTED', $h->view['active_item']['support_status']);
            $answer();                                                // third correct answer, but after a hint
            $this->assertSame('TARGET_ASSESSMENT', $h->mode());
            $this->assertSame([], self::criterionMessages($h->view['messages']));
            $this->assertSame([], $h->events('CRITERION_MET'));
            $this->assertSame(0, $h->view['progress']['independent_successes'], 'the answer after the hint broke the run');

            $answer();                                                // 4
            $answer();                                                // 5
            $this->assertSame(['TARGET_ASSESSMENT', 2], [$h->mode(), $h->view['progress']['independent_successes']]);
            $this->assertSame([], self::criterionMessages($h->view['messages']));
            $r = $answer();                                           // 6
            $this->assertSame('SESSION_REPORT', $h->mode());
            $this->assertCount(1, self::criterionMessages($r->body['messages']));
            $met = $h->events('CRITERION_MET');
            $this->assertCount(1, $met);
            $responses = array_column(array_map(fn ($e) => ['id' => $e['event_id'], 'item' => $e['data']['item_id']], $h->events('RESPONSE_SUBMITTED')), 'item', 'id');
            $counted = array_map(fn ($id) => $responses[$id], $met[0]['data']['event_ids']);
            $this->assertSame(array_slice($answered, 3, 3), $counted, 'counted: answers 4, 5 and 6');
            $this->assertNotContains($hinted, $counted);
            $presented = array_column(array_map(fn ($e) => $e['data'], $h->events('ITEM_PRESENTED')), null, 'item_id');
            $supported = array_column(array_map(fn ($e) => $e['data'], $h->events('SUPPORT_GIVEN')), 'item_id');
            foreach ($counted as $item) {
                $this->assertSame('TARGET', $presented[$item]['role']);
                $this->assertTrue($presented[$item]['independent_eligible']);
                $this->assertFalse($presented[$item]['model_text_shown']);
                $this->assertNotContains($item, $supported);
            }
        } finally {
            $h->cleanup();
        }
    }

    /** B14b (ledger rule): an item recorded as shown with model text never counts toward the criterion. */
    public function testB14bItemShownWithModelTextIsNotCounted(): void
    {
        $events = [];
        $add = function (string $type, array $data, string $id) use (&$events): void {
            $events[] = ['event_id' => $id, 'type' => $type, 'data' => $data];
        };
        for ($i = 1; $i <= 3; $i++) {
            $add('ITEM_PRESENTED', ['item_id' => "i$i", 'item_version' => 1, 'skill_id' => 'N06', 'role' => 'TARGET', 'variant_id' => 'constructed',
                'episode_id' => 'ep', 'independent_eligible' => true, 'model_text_shown' => $i === 3], "p$i");
            $add('RESPONSE_SUBMITTED', ['item_id' => "i$i", 'item_version' => 1, 'response_sha256' => "h$i"], "r$i");
            $add('CHECKER_RESULT', ['result' => ['checker_result_id' => "c$i", 'event_id' => "r$i", 'item_id' => "i$i", 'item_version' => 1,
                'response_sha256' => "h$i", 'verdict' => 'CORRECT', 'independent_eligible' => true]], "k$i");
        }
        $computed = \AlgebraEngine\Evidence\CriteriaCalculator::compute($events);
        $this->assertSame([], $computed['met']);
        $this->assertSame(2, $computed['criteria'][0]['independent_correct_streak']);
    }
}
