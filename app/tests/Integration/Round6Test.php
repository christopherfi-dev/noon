<?php
declare(strict_types=1);

namespace AlgebraEngine\Tests\Integration;

use AlgebraEngine\Tests\Support\Harness;
use AlgebraEngine\Workflow\Engine;
use PHPUnit\Framework\TestCase;

/**
 * Conductor round 6 (§7), offline with the simulated model: the owner's four
 * live cases, the adversarial cases, and the teaching decisions. Mock
 * success here is not live-provider or learner validation.
 */
final class Round6Test extends TestCase
{
    /** A harness whose first E06 guided problem, after "I haven't learned this", is 4(x + 4). */
    private const GUIDED_4X4 = 'r6-guided-167';

    /** @return array{0:list<array>,1:list<string>,2:int} new events, shown texts, provider calls for one entry */
    private static function send(Harness $h, string $text, string $action = 'SUBMIT_RESPONSE'): array
    {
        $before = count($h->events());
        $calls = $h->mock->calls;
        $r = $h->event($action, ['text' => $text]);
        self::assertSame(200, $r->status, $text . ': ' . json_encode($r->body));
        return [array_slice($h->events(), $before), array_column($r->body['messages'], 'text'), $h->mock->calls - $calls];
    }

    private static function of(array $events, string $type): array
    {
        return array_values(array_filter($events, fn ($e) => $e['type'] === $type));
    }

    private static function verdicts(array $events): array
    {
        return array_map(fn ($e) => $e['data']['result']['verdict'], self::of($events, 'CHECKER_RESULT'));
    }

    private static function e06(int $a, int $b, string $seed): Harness
    {
        $h = new Harness([], null, null, null, null, $seed);
        $h->mock->faults[] = ['type' => 'ITEM_PATCH', 'patch' => ['parameters' => ['a' => $a, 'b' => $b]]];
        $h->start('E06');
        self::assertSame('Expand ' . $a . '(x + ' . $b . '). Write your answer without parentheses.', $h->active()['prompt']);
        return $h;
    }

    private static function guided4x4(): Harness
    {
        $h = new Harness([], null, null, null, null, self::GUIDED_4X4);
        $h->start('E06');
        $h->event('NOT_LEARNED');
        self::assertSame(['GUIDED_REPAIR', 's1'], [$h->mode(), $h->active()['guided_step']['id']]);
        self::assertSame('4(x + 4) means 4 groups of (x + 4). What is the total of the 4s in all 4 groups?', $h->active()['prompt']);
        return $h;
    }

    // ------------------------------------------------------------ owner's live cases

    /** Case 1: "9X + 8" on Expand 9(x + 8) is read as 9x + 8 by the host, scored wrong, with the x-only observation. */
    public function testCase1CapitalXIsReadAndTheDistributionErrorIsObserved(): void
    {
        $h = self::e06(9, 8, 'r6-case1');
        try {
            [$events, $shown, $calls] = self::send($h, '9X + 8');
            $read = self::of($events, 'ENTRY_INTERPRETED')[0]['data'];
            $this->assertSame(['CAPITAL_LETTER', '9x + 8', 'HOST'], [$read['kind'], $read['read_as'], $read['source']]);
            $this->assertSame(['INCORRECT'], self::verdicts($events));
            $this->assertContains('FIRST_TERM_ONLY', array_column(array_column(self::of($events, 'OBSERVATION'), 'data'), 'code'));
            $this->assertSame([], self::of($events, 'ENTRY_READ'), 'no model reading was needed');
            $this->assertSame([], self::of($events, 'SLIP_CANDIDATE'), 'a distribution error is not a slip');
            $this->assertSame(1, $calls, 'deterministic first: one provider call for this entry');
            $this->assertSame('Not yet.', $shown[0]);
            $this->assertContains('Your answer multiplied only the x by 9.', $shown);
            $this->assertSame('DIAGNOSTIC_PROBE', $h->mode());
            $learner = $h->request('GET', 'report')->body['summary']['learner'];
            $this->assertContains('Once you multiplied only the first term inside the parentheses.', $learner['trouble']);
        } finally {
            $h->cleanup();
        }
    }

    /** Case 2: "16 because it's 4 x 4" on the guided total-of-4s step is read as 16 and is correct. */
    public function testCase2AnswerWithAReasonIsReadAndCorrect(): void
    {
        $h = self::guided4x4();
        try {
            [$events, , $calls] = self::send($h, '16 because it\'s 4 x 4');
            $read = self::of($events, 'ENTRY_READ')[0]['data'];
            $this->assertSame(['answer', '16'], [$read['kind'], $read['normalized_answer']]);
            $this->assertContains('words', $read['triggers']);
            $this->assertSame(['CORRECT'], self::verdicts($events));
            $this->assertStringContainsString('Read from the student\'s wording as "16"', implode(' ', self::of($events, 'CHECKER_RESULT')[0]['data']['result']['limitations']));
            $this->assertSame(2, $calls, 'one reading call and one reply call');
            $this->assertSame('s2', $h->active()['guided_step']['id']);
        } finally {
            $h->cleanup();
        }
    }

    /** Case 3: "4 x x" on the guided how-many-x step is read as 4x (x used as times), is correct, and the reply names the rule. */
    public function testCase3XAsTimesIsReadAndTheReplyNamesTheRule(): void
    {
        $h = self::guided4x4();
        try {
            $h->answerCorrect();                                              // s1: 16
            $this->assertSame('How many x\'s are in all 4 groups? Write them as one term.', $h->active()['prompt']);
            [$events, $shown] = self::send($h, '4 x x');
            $read = self::of($events, 'ENTRY_READ')[0]['data'];
            $this->assertSame(['answer', '4*x', 'used x as a multiplication sign', '4 x x'], [$read['kind'], $read['normalized_answer'], $read['reading_note'], $read['host_reading']]);
            $this->assertSame(['CORRECT'], self::verdicts($events), 'on a guided step the accepted reading governs');
            $this->assertSame('Correct.', $shown[0]);
            $this->assertStringContainsString('the number outside the parentheses multiplies each term inside', $shown[1], 'the reply names the rule');
            $this->assertSame('MODEL', $h->row()['engine']['messages'][1]['source']);
        } finally {
            $h->cleanup();
        }
    }

    /** Case 4: "7x + 25" on Expand 7(x + 5) is a slip: micro-check, retry, and the run is paused, not reset. */
    public function testCase4SlipMicroCheckRetryAndPause(): void
    {
        $h = self::e06(7, 5, 'r6-case4');
        try {
            [$events, $shown, $calls] = self::send($h, '7x + 25');
            $this->assertSame(['INCORRECT'], self::verdicts($events));
            $slip = self::of($events, 'SLIP_CANDIDATE')[0]['data'];
            $this->assertSame(['7 × 5', 25, 'HOST'], [$slip['sub_step']['display'], $slip['wrong_value'], $slip['source']]);
            $this->assertSame(1, $calls, 'a host slip signature needs no decision call');
            $this->assertSame('What is 7 × 5?', $h->active()['prompt']);
            $this->assertSame(['Not yet.', 'Most of that was right. Let\'s check one part of it.'], $shown);
            $this->assertSame('TARGET_ASSESSMENT', $h->mode(), 'no probe: the run is paused');
            $this->assertStringContainsString('Your count is paused, not reset.', $h->view['progress']['label']);

            [$events, $shown] = self::send($h, '35');
            $this->assertSame(['CORRECT'], self::verdicts($events));
            $this->assertCount(1, self::of($events, 'SLIP_CONFIRMED'));
            $this->assertSame('Expand 7(x + 5). Write your answer without parentheses.', $h->active()['prompt'], 'the same question again');
            $this->assertSame(['Correct.', 'Yes. Okay, try it again.'], $shown);
            $this->assertFalse($h->active()['independent_eligible'], 'the retry counts neither way');

            [$events] = self::send($h, '7x + 35');
            $this->assertSame(['CORRECT'], self::verdicts($events));
            $this->assertCount(1, self::of($events, 'SLIP_RETRY_ANSWERED'));
            $this->assertSame(['TARGET_ASSESSMENT', 0], [$h->mode(), $h->view['progress']['independent_successes']]);
            $scored = \AlgebraEngine\Evidence\CriteriaCalculator::compute($h->events())['scored'];
            $this->assertSame([true], array_column(array_filter($scored, fn ($s) => $s['verdict'] === 'INCORRECT'), 'slip'), 'the slipped answer is a pause, not a wrong answer');

            $report = $h->request('GET', 'report')->body;
            $this->assertContains('You made a slip with 7 × 5 and then worked it out correctly, so your count was paused, not reset.', $report['summary']['learner']['trouble']);
            $this->assertStringContainsString('Slip at E06: one arithmetic error in 7 × 5 (the answer used 25)', $report['slips'][0]['statement']);
            $this->assertNotContains('E06: 1 answer was not right yet.', $report['summary']['learner']['trouble']);
        } finally {
            $h->cleanup();
        }
    }

    /** §5.4: a confirmed slip pauses a run of two; the next correct answer meets the goal (N05). */
    public function testSlipPausesARunOfTwo(): void
    {
        $h = new Harness([], null, null, null, null, 'r6-pause');
        try {
            $h->start('N05');
            $h->answerCorrect();
            $h->answerCorrect();
            $this->assertSame(2, $h->view['progress']['independent_successes']);
            $p = $h->active()['params'];
            $size = abs($p['a'] * $p['b']);
            $sign = ($p['a'] * $p['b']) < 0 ? '-' : '';
            [$events] = self::send($h, $sign . ($size + 1));                  // one away: a host slip signature
            $this->assertCount(1, self::of($events, 'SLIP_CANDIDATE'));
            $this->assertSame(2, $h->view['progress']['independent_successes'], 'paused, not reset');
            $h->answerCorrect();                                               // micro-check
            $h->answerCorrect();                                               // retry: counts neither way
            $this->assertSame([2, 'TARGET_ASSESSMENT'], [$h->view['progress']['independent_successes'], $h->mode()]);
            $h->answerCorrect();                                               // third counted answer
            $this->assertSame('SESSION_REPORT', $h->mode());
            $this->assertCount(1, $h->events('CRITERION_MET'));
        } finally {
            $h->cleanup();
        }
    }

    // ------------------------------------------------------------ adversarial (mock)

    /** A reading that adds a number is rejected; the entry is not scored. */
    public function testReadingThatAddsANumberIsRejected(): void
    {
        $h = self::guided4x4();
        try {
            $h->answerCorrect();
            $h->mock->faults[] = ['type' => 'READING', 'call' => 'interpret', 'set' => ['normalized_answer' => '14x', 'reading_note' => 'read as 14x']];
            [$events, $shown] = self::send($h, '4 x x');
            $rejected = self::of($events, 'READING_REJECTED')[0]['data'];
            $this->assertSame('14x', $rejected['proposed']);
            $this->assertStringContainsString('uses the number 14, which is not in what the learner wrote', implode(' ', $rejected['errors']));
            $this->assertSame(['UNSCORABLE'], self::verdicts($events));
            $this->assertSame([\AlgebraEngine\Workflow\EntryInterpreter::LINE_X_AS_TIMES], $shown);
            $this->assertSame('s2', $h->active()['guided_step']['id'], 'the same step stays open');
        } finally {
            $h->cleanup();
        }
    }

    /** A reading that turns a wrong value right is rejected: "7 times x plus 25" can never become 7x + 35. */
    public function testReadingThatTurnsAWrongValueRightIsRejected(): void
    {
        $h = self::e06(7, 5, 'r6-wrong-right');
        try {
            $h->mock->faults[] = ['type' => 'READING', 'call' => 'interpret', 'set' => ['normalized_answer' => '7x + 35']];
            [$events] = self::send($h, '7 times x plus 25');
            $this->assertStringContainsString('uses the number 35', implode(' ', self::of($events, 'READING_REJECTED')[0]['data']['errors']));
            $this->assertSame(['UNSCORABLE'], self::verdicts($events), 'not correct, and not scored as wrong');
            // Without the fault the same entry is read faithfully and checked.
            [$events] = self::send($h, '7 times x plus 25', 'CLARIFY_RESPONSE');
            $this->assertSame('7*x + 25', self::of($events, 'ENTRY_READ')[0]['data']['normalized_answer']);
            $this->assertSame(['INCORRECT'], self::verdicts($events));
        } finally {
            $h->cleanup();
        }
    }

    /** A proposed slip that the structure contradicts is rejected; one that the structure supports is accepted. */
    public function testModelSlipProposalsAreCheckedAgainstTheStructure(): void
    {
        $h = self::e06(7, 5, 'r6-slip-no');
        try {
            $h->mock->faults[] = ['type' => 'DECISION', 'call' => 'decision', 'set' => ['decision' => 'SLIP', 'choice' => 'product', 'reason' => 'probably a slip']];
            [$events, , $calls] = self::send($h, '7x + 12');
            $this->assertStringContainsString('not a near miss of 35', self::of($events, 'SLIP_PROPOSAL_REJECTED')[0]['data']['reason']);
            $this->assertSame([], self::of($events, 'SLIP_CANDIDATE'));
            $this->assertSame([2, 'DIAGNOSTIC_PROBE'], [$calls, $h->mode()], 'the ordinary wrong-answer path');
        } finally {
            $h->cleanup();
        }
        $h = self::e06(7, 5, 'r6-slip-yes');
        try {
            $h->mock->faults[] = ['type' => 'DECISION', 'call' => 'decision', 'set' => ['decision' => 'SLIP', 'choice' => 'product', 'reason' => '28 is 7 × 4']];
            [$events] = self::send($h, '7x + 28');
            $this->assertCount(1, self::of($events, 'SLIP_PROPOSAL_ACCEPTED'));
            $this->assertSame('MODEL', self::of($events, 'SLIP_CANDIDATE')[0]['data']['source']);
            $this->assertSame('What is 7 × 5?', $h->active()['prompt']);
        } finally {
            $h->cleanup();
        }
        // A conceptual pattern (only the x multiplied) is never offered as a slip: no decision call.
        $h = self::e06(7, 5, 'r6-slip-conceptual');
        try {
            [$events, , $calls] = self::send($h, '7x + 5');
            $this->assertSame([1, []], [$calls, self::of($events, 'TEACHING_DECISION')]);
        } finally {
            $h->cleanup();
        }
    }

    /** A smaller step with invented numbers is rejected and the planned step is used; a checked one replaces it. */
    public function testProposedStepsAreChecked(): void
    {
        $h = self::guided4x4();
        try {
            $h->mock->faults[] = ['type' => 'STEP_PROPOSAL', 'proposal' => ['question' => 'What is 9 × 13?', 'step_expression' => '9*13']];
            [$events] = self::send($h, '17');
            $rejected = self::of($events, 'STEP_PROPOSAL_REJECTED')[0]['data'];
            $this->assertStringContainsString('uses the number 9, which is not in the problem or an earlier step', implode(' ', $rejected['reasons']));
            $this->assertSame(['s1.1', 'What is 4 + 4 + 4 + 4?'], [$h->active()['guided_step']['id'], $h->active()['prompt']]);
        } finally {
            $h->cleanup();
        }
        $h = self::guided4x4();
        try {
            $h->mock->faults[] = ['type' => 'STEP_PROPOSAL', 'proposal' => ['question' => 'Is 4 + 4 equal to 8?', 'step_expression' => '4+4']];
            [$events] = self::send($h, '17');
            $this->assertContains('the question states its own answer', self::of($events, 'STEP_PROPOSAL_REJECTED')[0]['data']['reasons']);
        } finally {
            $h->cleanup();
        }
        $h = self::guided4x4();
        try {
            $h->mock->faults[] = ['type' => 'STEP_PROPOSAL', 'proposal' => ['question' => 'Two of the groups hold 4 + 4. What is that?', 'step_expression' => '4+4']];
            [$events] = self::send($h, '17');
            $this->assertCount(1, self::of($events, 'STEP_PROPOSAL_ACCEPTED'));
            $this->assertSame(['s1.1', 'Two of the groups hold 4 + 4. What is that?', '8'], [$h->active()['guided_step']['id'], $h->active()['prompt'], $h->correct()]);
            $h->answerCorrect();
            $this->assertSame('s1', $h->active()['guided_step']['id'], 'back to the step it broke down');
        } finally {
            $h->cleanup();
        }
    }

    /** A reply that leaks an answer is rejected before display: here, the micro-question's own answer. */
    public function testReplyLeakingTheAnswerIsRejected(): void
    {
        $h = self::e06(7, 5, 'r6-leak');
        try {
            $h->mock->faults[] = ['type' => 'STUDENT_MESSAGE', 'text' => 'Remember that 7 × 5 is 35, so the answer is 7x + 35.'];
            [$events, $shown] = self::send($h, '7x + 25');
            $this->assertStringContainsString('expected answer', implode(' ', self::of($events, 'MODEL_OUTPUT_REJECTED')[0]['data']['errors']));
            $this->assertSame('What is 7 × 5?', $h->active()['prompt']);
            $this->assertStringNotContainsString('35', implode(' ', $shown));
        } finally {
            $h->cleanup();
        }
    }

    /** A forward-looking reply before an independent item appears in the audit list (it passed the number check). */
    public function testForwardLookingReplyIsAudited(): void
    {
        $h = new Harness([], null, null, null, null, 'r6-audit');
        try {
            $h->start('N05');
            $text = 'You found the size and then the sign. Next time, remember to do it the same way.';
            $h->mock->faults[] = ['type' => 'STUDENT_MESSAGE', 'text' => $text];
            [$events, $shown] = self::send($h, $h->correct());
            $audit = self::of($events, 'REPLY_AUDIT')[0]['data'];
            $this->assertSame(['next', 'remember'], $audit['phrases']);
            $this->assertSame($text, $audit['text']);
            $this->assertSame(['Correct.', $text, Engine::INDEPENDENT_LEAD], $shown, 'shown, and listed for review');
            [$events] = self::send($h, $h->correct());
            $this->assertSame([], self::of($events, 'REPLY_AUDIT'), 'the simulated refer-back reply has no forward-looking phrase');
        } finally {
            $h->cleanup();
        }
    }

    /** On a scored item, when the host's parse and the model's reading disagree, one clarifying question is asked; the reply is checked. */
    public function testScoredDisagreementAsksOneClarifyingQuestion(): void
    {
        $h = self::e06(3, 4, 'r6-clarify');
        try {
            [$events, $shown] = self::send($h, '3 x x + 12');
            $asked = self::of($events, 'CLARIFICATION_ASKED')[0]['data'];
            $this->assertSame(['3 x x + 12', '3*x + 12', 'MODEL'], [$asked['host_reading'], $asked['model_reading'], $asked['source']]);
            $this->assertSame([], self::verdicts($events), 'nothing scored yet');
            $this->assertSame(['When you wrote x between two numbers or letters, did you mean times?'], $shown);
            $this->assertContains('CLARIFY_RESPONSE', $h->view['allowed_actions']);
            [$events] = self::send($h, 'yes', 'CLARIFY_RESPONSE');
            $this->assertSame(['CORRECT'], self::verdicts($events));
            $this->assertSame('3*x + 12', self::of($events, 'CHECKER_RESULT')[0]['data']['read_from_wording']['read_as']);
            $this->assertSame([], self::of($events, 'CLARIFICATION_ASKED'), 'only one clarifying question per item');
            $this->assertSame(1, $h->view['progress']['independent_successes']);
        } finally {
            $h->cleanup();
        }
    }

    /** A second slip in the same run resets it: no second micro-check. */
    public function testSecondSlipInARunResetsIt(): void
    {
        $h = new Harness([], null, null, null, null, 'r6-second');
        try {
            $h->start('N05');
            $h->answerCorrect();
            $slip = function () use ($h): array {
                $p = $h->active()['params'];
                $size = abs($p['a'] * $p['b']);
                return self::send($h, (($p['a'] * $p['b']) < 0 ? '-' : '') . ($size + 1));
            };
            [$events] = $slip();
            $this->assertCount(1, self::of($events, 'SLIP_CANDIDATE'));
            $h->answerCorrect();                                               // micro-check
            $h->answerCorrect();                                               // retry
            $this->assertSame(1, $h->view['progress']['independent_successes']);
            [$events] = $slip();
            $this->assertSame([], self::of($events, 'SLIP_CANDIDATE'));
            $this->assertContains('SECOND_SLIP_IN_RUN', array_column(array_column(self::of($events, 'OBSERVATION'), 'data'), 'code'));
            $this->assertSame([0, 'DIAGNOSTIC_PROBE'], [$h->view['progress']['independent_successes'], $h->mode()]);
        } finally {
            $h->cleanup();
        }
    }

    // ------------------------------------------------------------ teaching decisions

    /** §6.2: the model decides readiness within one to four guided problems; the fourth is the host's maximum. */
    public function testReadinessDecisions(): void
    {
        $h = self::guided4x4();
        try {
            $h->mock->faults[] = ['type' => 'DECISION', 'call' => 'decision', 'set' => ['decision' => 'INDEPENDENT_CHECK', 'reason' => 'Ready after one problem.']];
            $h->completeGuidedRepair();
            $this->assertSame('INDEPENDENT_CHECK', $h->mode());
            $d = $h->events('TEACHING_DECISION');
            $this->assertSame(['READINESS', 'MODEL', 'INDEPENDENT_CHECK'], [end($d)['data']['question'], end($d)['data']['source'], end($d)['data']['decision']]);
            $this->assertCount(1, $h->events('GUIDED_PROBLEM_COMPLETED'), 'the host minimum is one problem');
        } finally {
            $h->cleanup();
        }
        $h = self::guided4x4();
        try {
            for ($i = 0; $i < 4; $i++) {
                $h->mock->faults[] = ['type' => 'DECISION', 'call' => 'decision', 'set' => ['decision' => 'ANOTHER_GUIDED_PROBLEM', 'reason' => 'More practice.']];
            }
            $h->completeGuidedRepair(80);
            $this->assertSame('INDEPENDENT_CHECK', $h->mode());
            $this->assertCount(4, $h->events('GUIDED_PROBLEM_COMPLETED'), 'at most four guided problems per repair');
            $last = $h->events('TEACHING_DECISION');
            $this->assertSame(['HOST', 'INDEPENDENT_CHECK'], [end($last)['data']['source'], end($last)['data']['decision']]);
            $h->mock->faults = [];
        } finally {
            $h->cleanup();
        }
    }

    /** §6.3: with no matching error pattern, the model chooses an in-scope prerequisite from the host's list; an unlisted choice is rejected. */
    public function testPrerequisiteChoiceWhenNoPatternMatched(): void
    {
        foreach ([['N18', 'PREREQUISITE_CHECK'], ['A07', 'GUIDED_REPAIR']] as [$choice, $mode]) {
            $h = new Harness([], null, null, null, null, 'r6-prereq-' . $choice);
            try {
                $h->start('N06');
                $h->submit($h->wrong('NO_SIGNATURE'));
                $this->assertSame('DIAGNOSTIC_PROBE', $h->mode());
                $h->mock->faults[] = ['type' => 'DECISION', 'call' => 'decision', 'set' => ['decision' => 'CHECK_PREREQUISITE', 'choice' => $choice, 'reason' => 'Check the division facts.']];
                $h->submit($h->wrong('NO_SIGNATURE'));
                $this->assertSame($mode, $h->mode(), $choice);
                if ($choice === 'N18') {
                    $descent = $h->events('DESCENT')[0]['data'];
                    $this->assertSame(['N18', 'MODEL'], [$descent['to_skill'], $descent['chosen_by']]);
                } else {
                    $this->assertStringContainsString('choice must be one of the supplied choices: N05, N18', implode(' ', $h->events('MODEL_DECISION_REJECTED')[0]['data']['errors']));
                    $this->assertSame('NO_IMPLICATED_BRANCH', $h->events('BOUNDARY')[0]['data']['reason'], 'the host rule applies');
                }
            } finally {
                $h->cleanup();
            }
        }
    }

    /** §7: at most two provider calls per learner entry, and one for an ordinary answer. */
    public function testCallLimitPerEntry(): void
    {
        $h = self::e06(7, 5, 'r6-calls');
        try {
            for ($i = 0; $i < 3; $i++) {
                $h->mock->faults[] = ['type' => 'INVALID_JSON'];
            }
            [, , $calls] = self::send($h, '7 times x plus 25');                // a reading, then a reply that fails: no third call
            $this->assertSame(2, $calls);
            $this->assertSame('HOLD', $h->view['status']);
            $this->assertSame([], array_filter($h->events('CHECKER_RESULT'), fn ($e) => $e['data']['result']['verdict'] === 'CORRECT'));
        } finally {
            $h->cleanup();
        }
    }
}
