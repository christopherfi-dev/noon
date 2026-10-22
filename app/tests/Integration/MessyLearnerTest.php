<?php
declare(strict_types=1);

namespace AlgebraEngine\Tests\Integration;

use AlgebraEngine\Tests\Support\Harness;
use PHPUnit\Framework\TestCase;

/**
 * B11 (round 4 §4), offline: realistic learner inputs. Records the current
 * behaviour input by input; the parser and prompts are not changed here
 * (proposals go to the report). Unreadable input is never scored as wrong.
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

    public function testB11UnreadableInputsAreNeverScoredAsWrong(): void
    {
        $h = self::q07(3, 2, 15, 'b11-unreadable');                      // Solve 3(x + 2) = 15: x = 3
        try {
            $item = $h->active()['item_id'];
            $expected = [
                'idk' => 'That entry contains words or function names this question cannot read.',
                '' => 'Nothing was entered. Type your answer in the box.',
                'can you just tell me the answer' => 'That entry contains words or function names this question cannot read.',
                'is this on the test?' => 'That entry contains a symbol this question cannot read.',
                'three' => 'That entry contains words or function names this question cannot read.',
                '3]' => 'That entry contains a symbol this question cannot read.',      // typo …
            ];
            foreach ($expected as $text => $message) {
                [$verdict, $scope, $shown] = self::submit($h, $text);
                $this->assertSame('UNSCORABLE', $verdict, json_encode($text));
                $this->assertStringContainsString('Exact rational comparison', (string) $scope);
                $this->assertStringContainsString($message, $shown, json_encode($text));
                $this->assertSame($item, $h->active()['item_id'], 'the same item stays open');
            }
            [$verdict] = self::submit($h, '3');                                // … then the correction
            $this->assertSame('CORRECT', $verdict);
            $this->assertSame([], array_filter($h->events('CHECKER_RESULT'), fn ($e) => $e['data']['result']['verdict'] === 'INCORRECT'), 'nothing unreadable was scored as wrong');
            $this->assertSame(1, $h->view['progress']['independent_successes'], 'the correction counts once');
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
        // Current limit, proposed for the architect (not changed in round 4): a capital X is not read.
        $h = self::q07(3, 2, 15, 'b11-capital');
        try {
            $this->assertSame('UNSCORABLE', self::submit($h, 'X=3')[0]);
        } finally {
            $h->cleanup();
        }
    }
}
