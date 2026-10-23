<?php
declare(strict_types=1);

namespace AlgebraEngine\Tests\Integration;

use AlgebraEngine\Tests\Support\Harness;
use PHPUnit\Framework\TestCase;

/**
 * B11 (round 4 §4), offline: realistic learner inputs, input by input, with
 * the round 5 (R5-C) handling of stuck entries and the round 6 reading of
 * entries the host cannot read alone. Unreadable input is never scored as
 * wrong. Stuck entries are covered by B15 (StuckEntryTest).
 */
final class MessyLearnerTest extends TestCase
{
    /** @return array{0:string,1:?string,2:string} verdict, scope, tutor text for one submission */
    private static function submit(Harness $h, string $text): array
    {
        $before = count($h->events());
        $r = $h->event('SUBMIT_RESPONSE', ['text' => $text]);
        self::assertSame(200, $r->status, $text);
        $chk = array_values(array_filter(array_slice($h->events(), $before), fn ($e) => $e['type'] === 'CHECKER_RESULT'));
        return [$chk[0]['data']['result']['verdict'] ?? '-', $chk[0]['data']['result']['verification_scope'] ?? null, implode(' / ', array_column($r->body['messages'], 'text'))];
    }

    private static function q07(int $a, int $b, int $c, string $seed): Harness
    {
        $h = new Harness([], null, null, null, null, $seed);
        $h->mock->faults[] = ['type' => 'ITEM_PATCH', 'patch' => ['parameters' => ['a' => $a, 'b' => $b, 'c' => $c]]];
        $h->start('Q07');
        return $h;
    }

    /**
     * Round 6 (§3): entries the host cannot read go to the model, whose reading is checked. Nothing
     * unreadable is scored as wrong; an off-task question gets a reply and no verdict; a number
     * written as a word is read as that number and marked as read from the learner's wording.
     */
    public function testB11UnreadableInputsAreNeverScoredAsWrong(): void
    {
        $h = self::q07(3, 2, 15, 'b11-unreadable');                      // Solve 3(x + 2) = 15: x = 3
        try {
            $item = $h->active()['item_id'];
            // "idk" and "can you just tell me the answer" take the hint path (B15), so they are not on this item.
            $expected = [
                '' => ['UNSCORABLE', 'Nothing was entered. Type your answer in the box.'],
                'is this on the test?' => ['-', 'Let\'s stay with this problem for now.'],               // off task: a reply, no verdict
                '3]' => ['UNSCORABLE', 'I couldn\'t read that. Please write your answer again with numbers and symbols only.'],
            ];
            foreach ($expected as $text => [$want, $message]) {
                [$verdict, $scope, $shown] = self::submit($h, $text);
                $this->assertSame($want, $verdict, json_encode($text));
                $this->assertStringContainsString($message, $shown, json_encode($text));
                $this->assertSame($item, $h->active()['item_id'], 'the same item stays open');
                $this->assertSame('INDEPENDENT', $h->active()['support_status'], 'nothing here was help');
            }
            [$verdict] = self::submit($h, 'three');                            // … then the answer in words
            $this->assertSame('CORRECT', $verdict);
            $last = $h->events('CHECKER_RESULT');
            $this->assertStringContainsString('Read from the student\'s wording as "3"', implode(' ', end($last)['data']['result']['limitations']));
            $this->assertSame([], array_filter($h->events('CHECKER_RESULT'), fn ($e) => $e['data']['result']['verdict'] === 'INCORRECT'), 'nothing unreadable was scored as wrong');
            $this->assertSame(1, $h->view['progress']['independent_successes'], 'the answer counts once');
        } finally {
            $h->cleanup();
        }
    }

    public function testB11CorrectAnswersInLooseFormsAreAccepted(): void
    {
        foreach ([[3, 2, 15, 'x = 3'], [3, 2, 15, 'x=3'], [3, 2, 15, '3 '], [3, 2, 15, '  3  '], [3, 2, 15, '3.0'], [3, 2, 15, '6/2'], [3, 5, 6, '−3'], [3, 5, 6, 'x = −3']] as [$a, $b, $c, $text]) {
            $h = self::q07($a, $b, $c, 'b11-' . $text);
            try {
                [$verdict] = self::submit($h, $text);
                $this->assertSame('CORRECT', $verdict, json_encode($text, JSON_UNESCAPED_UNICODE));
            } finally {
                $h->cleanup();
            }
        }
        // Round 5 (R5-C): a capital X on a one-variable item in x is read as x; a trailing "?" is removed.
        foreach ([[3, 2, 15, 'X=3'], [3, 2, 15, 'X = 3'], [3, 2, 15, 'x = 3?'], [3, 5, 6, '-3?'], [3, 5, 6, 'X = -3?']] as [$a, $b, $c, $text]) {
            $h = self::q07($a, $b, $c, 'b11-' . $text);
            try {
                $this->assertSame('CORRECT', self::submit($h, $text)[0], $text);
                $this->assertSame(1, $h->view['progress']['independent_successes'], $text);
            } finally {
                $h->cleanup();
            }
        }
    }
}
