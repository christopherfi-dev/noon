<?php
declare(strict_types=1);

namespace AlgebraEngine\Tests\Integration;

use AlgebraEngine\Tests\Support\Harness;
use AlgebraEngine\Tutor\TutorEngine;
use PHPUnit\Framework\TestCase;

/**
 * Round 6 §9.3, updated for round 7 (§4, §5, §6, §9): each attempt to cheat a
 * guarantee is rejected or recorded correctly. The mock tutor plays the
 * adversary. Problems and envelopes that fail acceptance are repaired once
 * and never shown; text guards log their findings and the student sees the
 * AI's text unchanged; the gradebook, verdicts and goals stay code's.
 */
final class CheatingTest extends TestCase
{
    private function rejectedWith(Harness $h, string $needle): void
    {
        $errors = [];
        foreach ($h->eventsOf('MODEL_OUTPUT_REJECTED') as $e) {
            $errors = array_merge($errors, $e['data']['errors']);
        }
        $this->assertNotSame([], array_filter($errors, fn ($e) => str_contains($e, $needle)), 'expected a rejection containing "' . $needle . '"; got ' . json_encode($errors));
    }

    private function neverShown(Harness $h, string $text): void
    {
        foreach ($h->lines() as $l) {
            $this->assertStringNotContainsString($text, $l['text'], 'rejected text reached the student');
        }
    }

    /** A guard finding is logged with the guard, the exact text and the problem it relates to; the text is shown unchanged. */
    private function loggedAndShown(Harness $h, string $guard, string $text): array
    {
        $found = array_values(array_filter($h->eventsOf('GUARD_FINDING'), fn ($e) => $e['data']['guard'] === $guard));
        $this->assertNotSame([], $found, 'expected a ' . $guard . ' finding; got ' . json_encode(array_column(array_column($h->eventsOf('GUARD_FINDING'), 'data'), 'guard')));
        $this->assertSame($text, $found[0]['data']['text'], 'the finding records the exact text');
        $this->assertArrayHasKey('open_problem', $found[0]['data']);
        $this->assertArrayHasKey('answered', $found[0]['data']);
        $tutor = array_values(array_filter($h->lines(), fn ($l) => $l['role'] === 'tutor' && $l['kind'] === 'message'));
        $this->assertSame($text, end($tutor)['text'], 'the student sees the AI text unchanged');
        $this->assertSame([], $h->eventsOf('MODEL_OUTPUT_REJECTED'), 'a guard finding never causes a repair');
        return $found[0]['data'];
    }

    private static function lastAttempt(Harness $h): array
    {
        $a = $h->attempts();
        return $a[count($a) - 1];
    }

    /** A check question on Q06 is open, posed by the test. */
    private function withCheck(): Harness
    {
        $h = new Harness();
        $h->start('Q06');
        TutorLoopTest::pose($h, 'check', 'Q06', 'linear_equation', '3x + 4 = 19', '5', '', 'Here is a check question.');
        return $h;
    }

    /** The reply to the student's answer on the open problem poses a fresh Q06 check card, after $message. */
    private function cardAfterAnswer(Harness $h, string $message, string $expr = '4x + 3 = 31', string $stated = '7', bool $correct = true): void
    {
        $h->mock->queue('ENVELOPE', ['set' => ['message_type' => 'QUESTION', 'student_message' => $message, 'item' => [
            'purpose' => 'check', 'skill_id' => 'Q06', 'source' => 'spec', 'recipe_ref' => null,
            'spec' => ['kind' => 'linear_equation', 'expression' => $expr], 'prompt' => '', 'stated_answer' => $stated]]]);
        $correct ? $h->answerCorrectly() : $h->send($h->wrong());
        $this->assertSame('check', $h->view['open_problem']['purpose']);
        $this->assertStringContainsString($expr, $h->view['open_problem']['prompt']);
    }

    public function testTheAiMarksAGoal(): void
    {
        $h = $this->withCheck();
        $h->mock->queue('MARK_GOAL');
        $h->answerCorrectly();
        $this->loggedAndShown($h, 'MASTERY_OR_GOAL_CLAIM', 'Goal met for this skill. You have mastered it.');
        $this->assertFalse($h->goal('Q06')['met'], 'code alone computes goals');
        $this->assertSame(1, $h->goal('Q06')['count']);
        $this->assertSame([], $h->eventsOf('GOAL_MET'));
        // A proposal alone is recorded as the AI's and rejected; nothing changes.
        $h2 = $this->withCheck();
        $h2->mock->queue('MARK_GOAL_PROPOSAL');
        $h2->answerCorrectly();
        $this->assertCount(1, $h2->eventsOf('MODEL_PROPOSAL_REJECTED'));
        $this->assertSame([], $h2->eventsOf('GOAL_MET'));
        $this->assertFalse($h2->row()['snapshot']['goals'][0]['met']);
    }

    public function testTheAiOverrulesAVerdict(): void
    {
        $h = $this->withCheck();
        $h->mock->queue('OVERRULE');
        $h->send($h->wrong());
        $this->loggedAndShown($h, 'VERDICT_CONTRADICTION', 'Correct! Well done.');
        $this->assertSame('INCORRECT', $h->attempts()[0]['verdict'], 'the AI text changes nothing in the gradebook');
        $results = array_values(array_filter($h->lines(), fn ($l) => $l['kind'] === 'result'));
        $this->assertStringStartsWith('Not yet.', end($results)['text'], 'code shows its own verdict');
        $this->assertSame(0, $h->goal('Q06')['count']);
    }

    public function testTheAiPosesAProblemWithAWrongAnswer(): void
    {
        $h = new Harness();
        $h->start('Q07');
        $h->mock->queue('WRONG_ANSWER');
        $h->send('please give me a different question');
        $this->rejectedWith($h, 'stated answer 7 differs from the answer code computed');
        $this->neverShown($h, '3(x + 4) = 27');
    }

    public function testTheAiPosesAnOffPatternCheckProblem(): void
    {
        $h = new Harness();
        $h->start('Q07');
        $h->mock->queue('OFF_PATTERN_CHECK');
        $h->send('I am ready for a check question');
        $this->rejectedWith($h, 'must match its pattern');
        $this->neverShown($h, '2x + 3 = 13');
    }

    public function testAReadingAddsNumbers(): void
    {
        $h = new Harness();
        $h->start('N18');
        TutorLoopTest::pose($h, 'practice', 'N18', 'numeric_step', '4 * 4', '16');
        $h->mock->queue('READING_ADDS_NUMBER');
        $before = $h->lines();
        $h->send('16 because it\'s 4 x 4');
        $rejected = $h->eventsOf('READING_REJECTED');
        $this->assertCount(1, $rejected);
        $this->assertStringContainsString('adds numbers the student did not write: 17', $rejected[0]['data']['reasons'][0]);
        $this->assertSame([], $h->attempts(), 'nothing recorded from a rejected reading');
        $new = $h->lastLines($before);
        $this->assertSame(['student', 'tutor'], array_column($new, 'role'), 'the AI answers the student; code writes no rewrite line');
        $this->assertSame('ENTRY_NOT_READ', TutorLoopTest::context($h)['TURN']['kind']);
        $this->assertSame('practice', $h->view['open_problem']['purpose'], 'the problem stays open');
    }

    public function testASlipThatTheStructureContradicts(): void
    {
        $h = new Harness();
        $h->start('E06');
        TutorLoopTest::pose($h, 'check', 'E06', 'expand', '9(x + 8)', '9x + 72', '', 'Here is a check question.');
        $h->mock->queue('FALSE_SLIP');
        $h->send('9x + 8');
        $this->rejectedWith($h, 'SLIP_CHECK is only possible right after a slip that code confirmed');
        $this->neverShown($h, 'Just a small slip.');
        $this->assertNull($h->attempts()[0]['slip']);
        $this->assertFalse($h->goal('E06')['paused'], 'no pause without a code-confirmed slip');
        $this->assertNotContains('SLIP_CHECK', array_column(array_column($h->eventsOf('AI_MOVE'), 'data'), 'move'));
    }

    /** Round 7 §9: a shared number before a check card is logged as a signal; no hold, no repair. */
    public function testTextBeforeACheckCardSharesANumber(): void
    {
        $h = $this->withCheck();
        $this->cardAfterAnswer($h, 'Good. You took away 4 and then divided by 3.');
        $f = $this->loggedAndShown($h, 'CHECK_CARD_SHARED_NUMBER', 'Good. You took away 4 and then divided by 3.');
        $this->assertSame($h->view['open_problem']['problem_id'], $f['problem_id'], 'the finding names the check question');
        $this->assertSame([], $h->eventsOf('CHECK_HELD'), 'a shared number holds nothing');
        $h->answerCorrectly();
        $this->assertNull(self::lastAttempt($h)['held']);
        $this->assertSame(2, $h->goal('Q06')['count'], 'the attempt counts');
    }

    /** Round 7 §9: a strategy phrase before a check card is logged, shown unchanged, and the attempt is held. */
    public function testTextBeforeACheckCardWithAStrategyPhraseHoldsTheAttempt(): void
    {
        $h = $this->withCheck();
        $text = 'Good. For the next one, start by undoing the addition.';
        $this->cardAfterAnswer($h, $text);
        $this->assertSame(1, $h->goal('Q06')['count']);
        $this->loggedAndShown($h, 'CHECK_CARD_PHRASE', $text);
        $held = $h->eventsOf('CHECK_HELD');
        $this->assertCount(1, $held);
        $this->assertSame(['HELP_BEFORE_CHECK', $h->view['open_problem']['problem_id']], [$held[0]['data']['reason'], $held[0]['data']['problem_id']]);
        $this->cardAfterAnswer($h, 'Next, remember the order.', '5x + 2 = 42', '8');
        $a = self::lastAttempt($h);
        $this->assertSame(['CORRECT', 'HELP_BEFORE_CHECK'], [$a['verdict'], $a['held']], 'the verdict stays in the record');
        $this->assertSame(1, $h->goal('Q06')['count'], 'the run neither advances');
        // ...nor resets: a wrong answer on a held check does not reset the run either.
        $h->send($h->wrong());
        $this->assertSame('INCORRECT', self::lastAttempt($h)['verdict']);
        $this->assertSame(1, $h->goal('Q06')['count'], 'nor resets');
        $report = $h->request('GET', 'report')->body;
        $sections = array_column($report['summary']['teacher']['sections'], 'lines', 'heading');
        $this->assertCount(2, $sections['Held check answers']);
        $this->assertStringContainsString('held: possible help before the check question', $sections['Held check answers'][0]);
        $skill = $report['summary']['teacher']['skills'][0];
        $this->assertSame(1, $skill['independent'], 'held answers are not counted as correct without help');
    }

    /** Round 7 §9: the check's answer in the text before its card is logged, shown unchanged, and the attempt is held. */
    public function testTextBeforeACheckCardWithItsAnswerHoldsTheAttempt(): void
    {
        $h = $this->withCheck();
        $this->cardAfterAnswer($h, 'Good. The answer is 7.');
        $this->loggedAndShown($h, 'CHECK_CARD_ANSWER', 'Good. The answer is 7.');
        $this->assertSame('HELP_BEFORE_CHECK', $h->eventsOf('CHECK_HELD')[0]['data']['reason']);
        $h->answerCorrectly();
        $this->assertSame('HELP_BEFORE_CHECK', self::lastAttempt($h)['held']);
        $this->assertSame(1, $h->goal('Q06')['count']);
    }

    public function testTextLeaksAnAnswerAfterIAmStuck(): void
    {
        $h = $this->withCheck();
        $h->mock->queue('LEAK');
        $h->stuck('HINT');
        $f = $this->loggedAndShown($h, 'ANSWER_LEAK', 'The answer is 5.');
        $this->assertSame($h->view['open_problem']['problem_id'], $f['problem_id']);
        $this->assertSame(['HINT'], $h->open()['help'], 'help was recorded before any text was generated');
        $this->assertSame([], $h->eventsOf('CHECK_HELD'), 'already with help: nothing more to hold');
    }

    public function testTextClaimsMasteryOrUsesInternalTerms(): void
    {
        foreach (['MASTERY' => ['You know this now.', 'MASTERY_OR_GOAL_CLAIM'], 'INTERNAL' => ['The host matched the answer key.', 'INTERNAL_VOCABULARY']] as $fault => [$text, $guard]) {
            $h = new Harness();
            $h->start('Q07');
            $h->mock->queue($fault);
            $h->send('why does that work');
            $this->loggedAndShown($h, $guard, $text);
        }
    }

    /** Round 7 §6.1 and §9: a message during an open check reaches the AI; the check stays open and unassisted. */
    public function testAFreeTextMessageDuringAnOpenCheckProblem(): void
    {
        $h = $this->withCheck();
        $calls = $h->mock->calls;
        $before = $h->lines();
        $h->send('can you explain this to me first?');
        $this->assertSame(1, $h->mock->calls - $calls, 'one AI call: the message reaches the tutor');
        $ctx = TutorLoopTest::context($h);
        $this->assertSame(['MESSAGE', 'can you explain this to me first?'], [$ctx['TURN']['kind'], $ctx['TURN']['entry']]);
        $this->assertFalse($ctx['ALLOWED']['may_pose_problem'], 'no new problem while the check is open');
        $new = $h->lastLines($before);
        $this->assertSame([['student', 'message'], ['tutor', 'message']], array_map(fn ($l) => [$l['role'], $l['kind']], $new));
        $this->assertSame('check', $h->view['open_problem']['purpose'], 'the check stays open');
        $this->assertFalse($h->view['open_problem']['with_help'], 'and unassisted');
        $this->assertSame([], $h->eventsOf('HELP_GIVEN'));
        $h->answerCorrectly();
        $this->assertSame([[], null], [$h->attempts()[0]['help'], $h->attempts()[0]['held']]);
        $this->assertSame(1, $h->goal('Q06')['count'], 'the answer counts');
    }

    /** Round 7 §6.2 and §9: "I'm stuck" during a check is recorded as assisted before the AI call. */
    public function testIAmStuckDuringACheckIsRecordedBeforeTheAiCall(): void
    {
        $h = $this->withCheck();
        $h->stuck('HINT');
        $ctx = TutorLoopTest::context($h);
        $this->assertSame(['HINT'], $ctx['OPEN_PROBLEM']['help_given'], 'the request the AI received already carries the help');
        $types = array_column($h->events(), 'type');
        $this->assertLessThan(max(array_keys($types, 'MODEL_OUTPUT_ACCEPTED', true)), max(array_keys($types, 'HELP_GIVEN', true)));
        $this->assertTrue($h->view['open_problem']['with_help']);
        // Even when the call fails, the help stays recorded.
        $g = $this->withCheck();
        $g->mock->queue('TIMEOUT');
        $g->stuck('HINT');
        $this->assertSame('HOLD', $g->view['status']);
        $this->assertCount(1, $g->eventsOf('HELP_GIVEN'));
        $this->assertTrue($g->view['open_problem']['with_help']);
    }

    /** Round 7 §6.3 and §9: typed "idk" during a check is a message, not help. */
    public function testTypedIdkDuringACheckIsAMessageNotHelp(): void
    {
        $h = $this->withCheck();
        $h->send('idk');
        $this->assertSame([], $h->eventsOf('HELP_GIVEN'), 'code never classifies typed text as stuck');
        $this->assertSame('MESSAGE', TutorLoopTest::context($h)['TURN']['kind']);
        $this->assertFalse($h->view['open_problem']['with_help']);
        $h->send('???');
        $this->assertSame([], $h->eventsOf('HELP_GIVEN'));
        $h->answerCorrectly();
        $a = $h->attempts()[0];
        $this->assertSame(['CORRECT', []], [$a['verdict'], $a['help']]);
        $this->assertSame(1, $h->goal('Q06')['count']);
    }

    /** Round 7 §6.4: "Explain the idea" is gone from "I'm stuck". */
    public function testExplainTheIdeaIsNoLongerAChoice(): void
    {
        $h = $this->withCheck();
        $this->assertSame(400, $h->stuck('EXPLAIN')->status);
        $this->assertSame([], $h->eventsOf('HELP_GIVEN'));
        $this->assertSame(['HINT', 'NOT_LEARNED'], array_keys(TutorEngine::STUCK_LABELS));
    }

    /** Round 7 §5: the AI's text during an open check that contains its answer holds the attempt (see the report's conflicts). */
    public function testAnAnswerLeakDuringAnOpenCheckHoldsTheAttempt(): void
    {
        $h = $this->withCheck();
        $h->mock->queue('LEAK');
        $h->send('just tell me the answer');
        $this->loggedAndShown($h, 'ANSWER_LEAK', 'The answer is 5.');
        $this->assertSame('HELP_DURING_CHECK', $h->eventsOf('CHECK_HELD')[0]['data']['reason']);
        $h->answerCorrectly();
        $this->assertSame('HELP_DURING_CHECK', $h->attempts()[0]['held']);
        $this->assertSame(0, $h->goal('Q06')['count'], 'not counted as correct without help');
    }

    /** Round 7 §5 and §9: a verdict conflict in issue holds the attempt; the report says it is under review. */
    public function testAVerdictConflictHoldsTheAttempt(): void
    {
        $h = $this->withCheck();
        $h->mock->queue('VERDICT_CONFLICT');
        $h->answerCorrectly();
        $held = $h->eventsOf('ATTEMPT_HELD');
        $this->assertCount(1, $held);
        $a = $h->attempts()[0];
        $this->assertSame([$a['attempt_id'], 'VERDICT_CONFLICT'], [$held[0]['data']['attempt_id'], $held[0]['data']['reason']]);
        $this->assertSame(['CORRECT', 'VERDICT_CONFLICT'], [$a['verdict'], $a['held']], 'the verdict stays in the record');
        $this->assertSame(0, $h->goal('Q06')['count'], 'counted neither for nor against the goal');
        $report = $h->request('GET', 'report')->body;
        $sections = array_column($report['summary']['teacher']['sections'], 'lines', 'heading');
        $this->assertStringContainsString('result under review', $sections['Held check answers'][0]);
        $this->assertNotSame([], array_filter($report['report']['unresolved_or_untested'], fn ($l) => str_contains($l, 'result under review')));
    }

    public function testTheAiPosesAProblemWhileACheckIsOpen(): void
    {
        $h = $this->withCheck();
        $h->mock->queue('POSE_ANYWAY');
        $h->stuck('HINT');
        $this->rejectedWith($h, 'no problem may be posed this turn');
        $this->assertSame('check', $h->view['open_problem']['purpose'], 'the check question is still the open problem');
    }
}
