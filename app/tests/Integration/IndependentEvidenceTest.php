<?php
declare(strict_types=1);

namespace AlgebraEngine\Tests\Integration;

use AlgebraEngine\Workflow\Engine;
use AlgebraEngine\Tests\Support\Harness;
use PHPUnit\Framework\TestCase;

/**
 * Round 5, R5-A, revised in round 6 (§4): no help on independent items. The
 * reply before one may only refer back to the previous answer. B14 is also
 * checked by the harness after every request of every integration test;
 * these tests add the rejection record, journey coverage and the criterion
 * message (B14b).
 */
final class IndependentEvidenceTest extends TestCase
{
    private const CUE = 'Work out the sizes first, then settle the sign.';

    /** Criterion messages that say the work was done without help. */
    private static function criterionMessages(array $messages): array
    {
        return array_values(array_filter(array_column($messages, 'text'), fn ($t) => str_contains($t, 'correctly, without help. That meets')));
    }

    /**
     * B14, replaced in round 6 (§4): across B1 and B3 journeys, model text in the same turn as an
     * INDEPENDENT item refers only to the previous answer (the harness re-checks its numbers after
     * every request), comes before the host line, and does not make the item assisted. With no
     * answer to refer back to, the model's text is discarded unread.
     */
    public function testB14ModelTextBeforeIndependentItemsRefersBackOnly(): void
    {
        $checked = 0;
        $replies = 0;
        for ($i = 0; $i < 12; $i++) {
            $h = new Harness([], null, null, null, null, "b14-$i");
            try {
                $h->start('Q07');
                $wrong = $i % 2 ? ['DIST_X_ONLY', 'DIST_X_ONLY', 'FIRST_TERM_ONLY'] : ['SIGN_ERROR_B', 'SIGN_ERROR_B', 'MULTIPLY_INSTEAD_OF_DIVIDE', 'QUOTIENT_SIGN', 'NO_SIGNATURE'];
                $seen = [];
                $record = function () use ($h, &$seen): void {
                    $a = $h->row()['engine']['active'];
                    if ($a !== null && $a['role'] !== 'GUIDED' && !isset($a['slip_stage'])) {
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
                    $this->assertNotContains(self::CUE, $texts, 'text with nothing to refer back to is never shown');
                }
                $presented = array_filter($h->events('ITEM_PRESENTED'), fn ($e) => $e['data']['role'] !== 'GUIDED' && !isset($e['data']['slip_stage']));
                foreach ($presented as $e) {
                    $this->assertFalse($e['data']['model_text_shown']);
                    $replies += $e['data']['refer_back_reply'] ? 1 : 0;
                }
                $this->assertGreaterThan(0, count($h->events('MODEL_TEXT_DISCARDED')), 'the first item has no answer to refer back to');
                $checked += $h->b14Checked;
            } finally {
                $h->cleanup();
            }
        }
        $this->assertGreaterThan(100, $checked);
        $this->assertGreaterThan(100, $replies, 'refer-back replies were shown and re-checked');
    }

    /** B14 (round 6): a reply before an independent item that uses a number outside the previous problem and answer is rejected before display. */
    public function testB14ReplyWithANewNumberIsRejected(): void
    {
        $h = new Harness();
        try {
            $h->start('N06');
            $this->assertCount(1, $h->events('MODEL_TEXT_DISCARDED'), 'at the start there is no answer to refer back to');
            $this->assertSame([Engine::INDEPENDENT_LEAD], array_column($h->view['messages'], 'text'));
            $this->assertStringNotContainsString(self::CUE, json_encode($h->row()['engine']));
            $forward = 'Next time, try 997 divided by 13 first.';
            $h->mock->faults[] = ['type' => 'STUDENT_MESSAGE', 'text' => $forward];
            $h->answerCorrect();
            $this->assertSame('TARGET_ASSESSMENT', $h->mode());
            $errors = implode(' ', $h->events('MODEL_OUTPUT_REJECTED')[0]['data']['errors']);
            $this->assertStringContainsString('may only refer back to the previous answer: it uses 997', $errors);
            $texts = array_column($h->view['messages'], 'text');
            $this->assertNotContains($forward, $texts);
            $this->assertSame('Correct.', $texts[0]);
            $this->assertSame(Engine::INDEPENDENT_LEAD, end($texts));
            $this->assertStringNotContainsString('997', json_encode($h->row()['engine']));
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
