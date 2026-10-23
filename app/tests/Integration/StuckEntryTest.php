<?php
declare(strict_types=1);

namespace AlgebraEngine\Tests\Integration;

use AlgebraEngine\Tests\Support\Harness;
use AlgebraEngine\Workflow\EntryInterpreter;
use PHPUnit\Framework\TestCase;

/**
 * B15 (round 5, R5-C): stuck and off-task entries. Every row of the table in
 * both columns (guided repair; an independent item), the trailing-"?" rule
 * and the normalisation. No table entry is ever scored as incorrect.
 * Round 6 (§3, §4): the model writes the reply to these entries, told what
 * follows; questions, off-task entries and numbers written as words are read
 * by the model and checked by the host.
 */
final class StuckEntryTest extends TestCase
{
    private const HELP_LINE = 'This question now counts as practice with help, not as independent work.';

    /** Submits $text and returns the new events and the messages shown. @return array{0:list<array>,1:list<string>} */
    private static function send(Harness $h, string $text): array
    {
        $before = count($h->events());
        $r = $h->event('SUBMIT_RESPONSE', ['text' => $text]);
        self::assertSame(200, $r->status, $text . ': ' . json_encode($r->body));
        return [array_slice($h->events(), $before), array_column($r->body['messages'], 'text')];
    }

    private static function types(array $events): array
    {
        return array_column($events, 'type');
    }

    private static function verdicts(array $events): array
    {
        return array_map(fn ($e) => $e['data']['result']['verdict'], array_values(array_filter($events, fn ($e) => $e['type'] === 'CHECKER_RESULT')));
    }

    private static function interpreted(array $events): ?string
    {
        foreach ($events as $e) {
            if ($e['type'] === 'ENTRY_INTERPRETED') {
                return $e['data']['kind'];
            }
        }
        return null;
    }

    /** A Q07 target item: Solve 3(x + 2) = 15 (x = 3). */
    private static function target(string $seed): Harness
    {
        $h = new Harness([], null, null, null, null, $seed);
        $h->mock->faults[] = ['type' => 'ITEM_PATCH', 'patch' => ['parameters' => ['a' => 3, 'b' => 2, 'c' => 15]]];
        $h->start('Q07');
        return $h;
    }

    /** Guided repair at E06, first step s1 (which has a smaller question). */
    private static function guided(string $seed): Harness
    {
        $h = new Harness([], null, null, null, null, $seed);
        $h->start('Q07');
        $h->submit($h->wrong('DIST_X_ONLY'));
        $h->submit($h->wrong('DIST_X_ONLY'));
        $h->submit($h->wrong('FIRST_TERM_ONLY'));
        self::assertSame(['GUIDED_REPAIR', 's1'], [$h->mode(), $h->active()['guided_step']['id']]);
        return $h;
    }

    /** Rows 1–2, guided repair column: a smaller question (D1); row 2 first shows its host line. Never scored. */
    public function testStuckAndAskForAnswerInGuidedRepair(): void
    {
        $entries = array_merge(
            array_map(fn ($e) => [$e, 'STUCK'], array_merge(EntryInterpreter::STUCK, ['?', 'IDK.', 'I don\'t know', "I don\u{2019}t KNOW!!", '  no   idea  ', 'Help?'])),
            array_map(fn ($e) => [$e, 'ASK_FOR_ANSWER'], array_merge(EntryInterpreter::ASK_FOR_ANSWER, ['Can you just tell me the answer?', "What's the answer?"])),
        );
        foreach ($entries as $i => [$text, $kind]) {
            $h = self::guided("b15-guided-$i");
            try {
                [$events, $shown] = self::send($h, $text);
                $this->assertSame($kind, self::interpreted($events), $text);
                $this->assertSame([], self::verdicts($events), 'not scored: ' . $text);
                $this->assertContains('ITEM_CLOSED', self::types($events));
                $this->assertSame('GUIDED_REPAIR', $h->mode());
                $this->assertSame('s1.1', $h->active()['guided_step']['id'], 'a smaller question: ' . $text);
                if ($kind === 'ASK_FOR_ANSWER') {
                    $this->assertStringStartsWith(EntryInterpreter::LINE_ASK_SMALLER, $shown[0], $text);
                    $this->assertSame('MODEL', $h->row()['engine']['messages'][0]['source'], 'round 6: the model writes the reply');
                } else {
                    $this->assertSame([], array_filter($shown, fn ($s) => str_starts_with($s, EntryInterpreter::LINE_ASK_SMALLER)));
                }
            } finally {
                $h->cleanup();
            }
        }
    }

    /** Rows 1–2, independent column: the existing hint path; the item becomes assisted; row 2 first shows its host line. */
    public function testStuckAndAskForAnswerOnIndependentItem(): void
    {
        $entries = array_merge(
            array_map(fn ($e) => [$e, 'STUCK'], array_merge(EntryInterpreter::STUCK, ['?', 'IDK.', 'I don\'t know', 'i dont know', ' Not sure! '])),
            array_map(fn ($e) => [$e, 'ASK_FOR_ANSWER'], array_merge(EntryInterpreter::ASK_FOR_ANSWER, ['Just tell me the answer!'])),
        );
        foreach ($entries as $i => [$text, $kind]) {
            $h = self::target("b15-ind-$i");
            try {
                $item = $h->active()['item_id'];
                [$events, $shown] = self::send($h, $text);
                $this->assertSame($kind, self::interpreted($events), $text);
                $this->assertSame([], self::verdicts($events), 'not scored: ' . $text);
                $support = array_values(array_filter($events, fn ($e) => $e['type'] === 'SUPPORT_GIVEN'));
                $this->assertCount(1, $support, $text);
                $this->assertSame(['HINT', 'STUCK_ENTRY'], [$support[0]['data']['kind'], $support[0]['data']['trigger']]);
                $this->assertSame(['TARGET_ASSESSMENT', $item, 'ASSISTED'], [$h->mode(), $h->active()['item_id'], $h->view['active_item']['support_status']], $text);
                // Round 6: the host line first (the item is assisted before the hint is shown), then the model's reply with the hint.
                $this->assertSame(self::HELP_LINE, $shown[0], $text);
                $this->assertCount(2, $shown, 'the hint follows');
                $this->assertSame($kind === 'ASK_FOR_ANSWER', str_starts_with($shown[1], EntryInterpreter::LINE_ASK_HINT), $text);
                // The assisted item does not count as independent work.
                $h->answerCorrect();
                $this->assertSame(0, $h->view['progress']['independent_successes'], $text);
            } finally {
                $h->cleanup();
            }
        }
    }

    /** Round 5.1 P1: asking for the answer shows the line that names what actually follows, in each case. */
    public function testAskForAnswerLineNamesWhatFollows(): void
    {
        $ask = 'can you just tell me the answer';
        // Independent item: a hint follows.
        $h = self::target('p1-hint');
        try {
            [, $shown] = self::send($h, $ask);
            $this->assertSame(self::HELP_LINE, $shown[0]);
            $this->assertStringStartsWith(EntryInterpreter::LINE_ASK_HINT, $shown[1]);
        } finally {
            $h->cleanup();
        }
        // Guided repair: a smaller question follows (s1 has s1.1).
        $h = self::guided('p1-smaller');
        try {
            [, $shown] = self::send($h, $ask);
            $this->assertSame(['GUIDED_REPAIR', 's1.1'], [$h->mode(), $h->active()['guided_step']['id']]);
            $this->assertStringStartsWith(EntryInterpreter::LINE_ASK_SMALLER, $shown[0]);
        } finally {
            $h->cleanup();
        }
        // Guided repair: the same question asked another way (s3 after its smaller question, with an alternate phrasing).
        $h = self::guided('p1-another-way');
        try {
            $h->answerCorrect();                                    // s1
            $h->answerCorrect();                                    // s2
            $h->submit($h->plainWrong());                           // s3 → s3.1
            $h->answerCorrect();                                    // s3.1 → s3 again
            $this->assertSame('s3', $h->active()['guided_step']['id']);
            [, $shown] = self::send($h, $ask);
            $this->assertSame('alt', $h->active()['params']['phrasing']);
            $this->assertCount(2, $shown);
            $this->assertStringStartsWith(EntryInterpreter::LINE_ASK_ANOTHER_WAY, $shown[0]);
            $this->assertSame('Here is the same question, asked another way.', $shown[1]);
        } finally {
            $h->cleanup();
        }
        // Guided repair: a prerequisite check follows (stuck at s1.1, which exercises N18, a declared prerequisite in the beta).
        $h = self::guided('p1-prerequisite');
        try {
            $h->submit($h->plainWrong());                           // s1 → s1.1
            [, $shown] = self::send($h, $ask);
            $this->assertSame(['PREREQUISITE_CHECK', 'N18'], [$h->mode(), $h->skill()]);
            $this->assertSame([EntryInterpreter::LINE_ASK_PREREQUISITE, \AlgebraEngine\Workflow\Engine::INDEPENDENT_LEAD], $shown);
        } finally {
            $h->cleanup();
        }
        // A boundary follows (s3.1 exercises A07, outside the beta), then a new guided problem: the architect's fifth line.
        $h = self::guided('p1-boundary');
        try {
            $h->answerCorrect();                                    // s1
            $h->answerCorrect();                                    // s2
            $h->submit($h->plainWrong());                           // s3 → s3.1
            [, $shown] = self::send($h, $ask);
            $this->assertSame(['GUIDED_REPAIR', 's1'], [$h->mode(), $h->active()['guided_step']['id']]);
            $this->assertStringStartsWith('I won\'t give the answer, but let\'s try a different problem.', $shown[0]);
            foreach ([EntryInterpreter::LINE_ASK_SMALLER, EntryInterpreter::LINE_ASK_ANOTHER_WAY, EntryInterpreter::LINE_ASK_PREREQUISITE, EntryInterpreter::LINE_ASK_HINT] as $line) {
                $this->assertSame([], array_filter($shown, fn ($s) => str_contains($s, $line)));
            }
        } finally {
            $h->cleanup();
        }
    }

    /**
     * Rows 3–4, revised in round 6 (§3): the model reads questions, off-task entries and numbers written
     * as words; the host checks each reading. Off-task entries get a reply and no verdict; a question
     * about an independent item is answered and the item becomes practice with help first; a number
     * word is read as that number and marked as read from the learner's wording. Never scored as
     * incorrect unless the reading is a wrong value.
     */
    public function testOffTaskQuestionAndNumberWords(): void
    {
        foreach (['independent' => fn ($s) => self::target($s), 'guided' => fn ($s) => self::guided($s)] as $column => $make) {
            foreach (['is this on the test?', 'Why do I need this??'] as $i => $text) {
                $h = $make("b15-$column-off-$i");
                try {
                    $item = $h->active()['item_id'];
                    $status = $h->view['active_item']['support_status'];
                    [$events, $shown] = self::send($h, $text);
                    $read = array_values(array_filter($events, fn ($e) => $e['type'] === 'ENTRY_READ'));
                    $this->assertSame('off_task', $read[0]['data']['kind'], $column . ': ' . $text);
                    $this->assertSame([], self::verdicts($events), 'no verdict: ' . $text);
                    $this->assertSame(['Let\'s stay with this problem for now. Type your answer, or type help if you\'re stuck.'], $shown);
                    $this->assertSame([$item, $status], [$h->active()['item_id'], $h->view['active_item']['support_status']], 'same item, same support status');
                } finally {
                    $h->cleanup();
                }
            }
            $h = $make("b15-$column-question");
            try {
                $item = $h->active()['item_id'];
                [$events, $shown] = self::send($h, 'What does it mean to expand?');
                $this->assertSame([], self::verdicts($events));
                $this->assertSame($item, $h->active()['item_id']);
                if ($column === 'independent') {
                    $this->assertSame([self::HELP_LINE, 'Good question. Look at what the question asks you to find, and start from what you already know about it.'], $shown);
                    $this->assertSame('ASSISTED', $h->view['active_item']['support_status'], 'answering a question is help, recorded before it is shown');
                    $this->assertSame('ANSWERED_QUESTION', $h->events('SUPPORT_GIVEN')[0]['data']['kind']);
                } else {
                    $this->assertCount(1, $shown);
                }
            } finally {
                $h->cleanup();
            }
        }
        // Numbers in words on Solve 3(x + 2) = 15: read, checked against the entry, scored and marked.
        foreach (['three' => ['3', 'CORRECT'], 'Negative three.' => ['-3', 'INCORRECT'], 'the answer is three' => ['3', 'CORRECT'], 'x is three' => ['x = 3', 'CORRECT']] as $text => [$reading, $verdict]) {
            $h = self::target('b15-word-' . $text);
            try {
                [$events] = self::send($h, $text);
                $read = array_values(array_filter($events, fn ($e) => $e['type'] === 'ENTRY_READ'));
                $this->assertSame($reading, $read[0]['data']['normalized_answer'], $text);
                $this->assertSame([$verdict], self::verdicts($events), $text);
                $result = array_values(array_filter($events, fn ($e) => $e['type'] === 'CHECKER_RESULT'))[0]['data'];
                $this->assertStringContainsString('Read from the student\'s wording as "' . $reading . '"', implode(' ', $result['result']['limitations']));
                $this->assertSame($text, $result['read_from_wording']['entry']);
            } finally {
                $h->cleanup();
            }
        }
        // Readings that cannot be checked are not scored: unscorable, never incorrect.
        $h = self::target('b15-other');
        try {
            foreach (['twenty one', 'thirty', 'negative'] as $text) {
                [$events, $shown] = self::send($h, $text);
                $this->assertSame(['UNSCORABLE'], self::verdicts($events), $text);
                $this->assertNotEmpty($shown, $text);
            }
        } finally {
            $h->cleanup();
        }
    }

    /** Row 5, both columns: X=3 or X = 3 on a one-variable item in x is read as x and scored (round 6: any capital of the item's only variable). */
    public function testCapitalXIsReadAsX(): void
    {
        foreach (['X=3', 'X = 3', 'X=3?'] as $i => $text) {
            $h = self::target("b15-x-$i");
            try {
                [$events] = self::send($h, $text);
                $this->assertSame('CAPITAL_LETTER', self::interpreted($events), $text);
                $this->assertSame(['CORRECT'], self::verdicts($events), $text);
                $this->assertSame(1, $h->view['progress']['independent_successes'], $text);
            } finally {
                $h->cleanup();
            }
        }
        // Guided column: Q04 repair; its step "… What is x?" is a one-variable step in x.
        $h = new Harness([], null, null, null, null, 'b15-x-guided');
        try {
            $h->start('Q04');
            $h->submit($h->plainWrong());
            $h->submit($h->plainWrong());
            $this->assertSame(['GUIDED_REPAIR', 'Q04'], [$h->mode(), $h->skill()]);
            for ($n = 0; $n < 6 && !str_contains($h->active()['prompt'], 'What is x?'); $n++) {
                $h->answerCorrect();
            }
            $this->assertStringContainsString('What is x?', $h->active()['prompt']);
            [$events] = self::send($h, 'X = ' . $h->correct());
            $this->assertSame('CAPITAL_LETTER', self::interpreted($events));
            $this->assertSame(['CORRECT'], self::verdicts($events));
            // Not in x: an arithmetic item keeps the current clarification for a capital X.
            $h2 = new Harness([], null, null, null, null, 'b15-x-n06');
            try {
                $h2->start('N06');
                [$events] = self::send($h2, 'X = ' . $h2->correct());
                $this->assertNull(self::interpreted($events));
                $this->assertSame(['UNSCORABLE'], self::verdicts($events));
            } finally {
                $h2->cleanup();
            }
        } finally {
            $h->cleanup();
        }
    }

    /** The trailing-"?" rule runs before the table: a readable answer is scored normally, right or wrong. */
    public function testTrailingQuestionMarkIsScoredNormally(): void
    {
        foreach ([['3?', 'CORRECT'], ['x = 3?', 'CORRECT'], ['x=3 ?', 'CORRECT'], ['6/2?', 'CORRECT'], ['4?', 'INCORRECT']] as $i => [$text, $verdict]) {
            $h = self::target("b15-q-$i");
            try {
                [$events] = self::send($h, $text);
                $this->assertSame('TRAILING_QUESTION_MARK', self::interpreted($events), $text);
                $this->assertSame([$verdict], self::verdicts($events), $text);
                $result = array_values(array_filter($events, fn ($e) => $e['type'] === 'CHECKER_RESULT'))[0]['data']['result'];
                $submitted = array_values(array_filter($events, fn ($e) => $e['type'] === 'RESPONSE_SUBMITTED'))[0];
                $this->assertSame($submitted['data']['response_sha256'], $result['response_sha256'], 'the verdict is bound to the text as submitted');
                $this->assertSame($text, $submitted['data']['text']);
            } finally {
                $h->cleanup();
            }
        }
        $h = new Harness([], null, null, null, null, 'b15-q-neg');
        try {
            $h->mock->faults[] = ['type' => 'ITEM_PATCH', 'patch' => ['parameters' => ['a' => 3, 'b' => 5, 'c' => 9]]];   // 3(x + 5) = 9: x = -2
            $h->start('Q07');
            [$events] = self::send($h, '-2?');
            $this->assertSame(['TRAILING_QUESTION_MARK', ['CORRECT']], [self::interpreted($events), self::verdicts($events)]);
        } finally {
            $h->cleanup();
        }
    }

    /** Normalisation: lowercase, trim, collapse spaces, remove apostrophes, strip trailing . ! ? */
    public function testNormalisation(): void
    {
        foreach (['IDK.' => 'idk', 'I don\'t know' => 'i dont know', 'i dont know' => 'i dont know', "  I DON\u{2019}T   know?!  " => 'i dont know',
            'Im STUCK...' => 'im stuck', "What's the answer?" => 'whats the answer', '?' => ''] as $in => $out) {
            $this->assertSame($out, EntryInterpreter::normalise($in), $in);
        }
    }

    /**
     * §4 flow after a stuck entry in each independent mode: the hint path, then the transition
     * the mode already defines for an assisted answer. Documents the flow for the report.
     */
    public function testStuckEntryFlowInEachIndependentMode(): void
    {
        $flows = [];
        $probe = function (Harness $h, string $mode) use (&$flows): void {
            $this->assertSame($mode, $h->mode());
            $skill = $h->skill();
            [$events] = self::send($h, 'idk');
            $this->assertSame(['STUCK', [], $mode, 'ASSISTED'], [self::interpreted($events), self::verdicts($events), $h->mode(), $h->view['active_item']['support_status']]);
            $h->answerCorrect();
            $flows[$mode] = $skill . ' → ' . $h->mode() . ' (' . $h->skill() . ')';
        };
        // TARGET_ASSESSMENT
        $h = new Harness([], null, null, null, null, 'b15-flow-1');
        try {
            $h->start('Q07');
            $probe($h, 'TARGET_ASSESSMENT');
        } finally {
            $h->cleanup();
        }
        // DIAGNOSTIC_PROBE
        $h = new Harness([], null, null, null, null, 'b15-flow-2');
        try {
            $h->start('Q07');
            $h->submit($h->wrong('DIST_X_ONLY'));
            $probe($h, 'DIAGNOSTIC_PROBE');
        } finally {
            $h->cleanup();
        }
        // PREREQUISITE_CHECK
        $h = new Harness([], null, null, null, null, 'b15-flow-3');
        try {
            $h->start('Q07');
            $h->submit($h->wrong('DIST_X_ONLY'));
            $h->submit($h->wrong('DIST_X_ONLY'));
            $probe($h, 'PREREQUISITE_CHECK');
        } finally {
            $h->cleanup();
        }
        // INDEPENDENT_CHECK and RETURN_TO_TARGET
        $h = new Harness([], null, null, null, null, 'b15-flow-4');
        try {
            $h->start('Q07');
            $h->submit($h->wrong('DIST_X_ONLY'));
            $h->submit($h->wrong('DIST_X_ONLY'));
            $h->submit($h->wrong('FIRST_TERM_ONLY'));
            $h->completeGuidedRepair();
            $probe($h, 'INDEPENDENT_CHECK');
            $h->passChecksAt('E06', 8);
            $probe($h, 'RETURN_TO_TARGET');
        } finally {
            $h->cleanup();
        }
        $this->assertSame([
            'TARGET_ASSESSMENT' => 'Q07 → TARGET_ASSESSMENT (Q07)',     // next target item; no criterion progress
            'DIAGNOSTIC_PROBE' => 'Q07 → DIAGNOSTIC_PROBE (Q07)',       // round 5.1 P4: inconclusive; one more fresh probe (B16)
            'PREREQUISITE_CHECK' => 'E06 → GUIDED_REPAIR (E06)',         // assisted check → bounded instructional trial
            'INDEPENDENT_CHECK' => 'E06 → INDEPENDENT_CHECK (E06)',      // next independent item; no criterion progress
            'RETURN_TO_TARGET' => 'Q07 → RETURN_TO_TARGET (Q07)',        // same kind of item again
        ], $flows);
    }
}
