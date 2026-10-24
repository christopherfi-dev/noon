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
        $this->rejectedWith($h, 'SLIP_CHECK is only possible right after an answer that code found eligible for the slip pause');
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

    /** Round 9 §3.2: a strategy phrase before a check card is logged as a signal and shown unchanged; nothing is held and the attempt counts. */
    public function testTextBeforeACheckCardWithAStrategyPhraseIsASignal(): void
    {
        $h = $this->withCheck();
        $text = 'Good. For the next one, start by undoing the addition.';
        $this->cardAfterAnswer($h, $text);
        $this->assertSame(1, $h->goal('Q06')['count']);
        $f = $this->loggedAndShown($h, 'CHECK_CARD_PHRASE', $text);
        $this->assertTrue($f['signal']);
        $this->assertSame([], $h->eventsOf('CHECK_HELD'), 'no detector holds a check');
        $h->answerCorrectly();
        $this->assertSame(['CORRECT', null], [self::lastAttempt($h)['verdict'], self::lastAttempt($h)['held']]);
        $this->assertSame(2, $h->goal('Q06')['count'], 'the attempt counts');
        $sections = array_column($h->request('GET', 'report')->body['summary']['teacher']['sections'], 'lines', 'heading');
        $this->assertSame([], $sections['Held check answers']);
        $this->assertStringContainsString('phrases directed at it: start by', $sections['Before check questions (audit)'][0]);
        $this->assertStringNotContainsString('held', $sections['Before check questions (audit)'][0]);
    }

    /** Round 9 §3.2: the check's answer in the text before its card is logged as a signal and shown unchanged; nothing is held. */
    public function testTextBeforeACheckCardWithItsAnswerIsASignal(): void
    {
        $h = $this->withCheck();
        $this->cardAfterAnswer($h, 'Good. The answer is 7.');
        $this->loggedAndShown($h, 'CHECK_CARD_ANSWER', 'Good. The answer is 7.');
        $this->assertSame([], $h->eventsOf('CHECK_HELD'));
        $h->answerCorrectly();
        $this->assertNull(self::lastAttempt($h)['held']);
        $this->assertSame(2, $h->goal('Q06')['count']);
    }

    /** Round 8 §3.3, §3.4 and §4: "Here is one more to try." before a card holds nothing, and "on your own" in that sentence raises no help claim. */
    public function testANeutralHandoverBeforeACheckCard(): void
    {
        $h = $this->withCheck();
        $text = 'Correct. Here is one more to try on your own.';
        $this->cardAfterAnswer($h, $text);
        $this->assertSame([], $h->eventsOf('CHECK_HELD'), 'a neutral invitation holds nothing');
        $guards = array_column(array_column($h->eventsOf('GUARD_FINDING'), 'data'), 'guard');
        $this->assertNotContains('HELP_CLAIM', $guards, 'no help claim in the handover sentence');
        $this->assertContains('CHECK_CARD_INVITATION', $guards, 'the invitation is logged');
        $invitation = array_values(array_filter($h->eventsOf('GUARD_FINDING'), fn ($e) => $e['data']['guard'] === 'CHECK_CARD_INVITATION'))[0]['data'];
        $this->assertSame(['try', 'one more'], $invitation['phrases'], 'phrase by phrase');
        $audits = $h->eventsOf('CHECK_CARD_AUDIT');
        $audit = end($audits)['data'];
        $this->assertSame([[], ['try', 'one more'], ['on your own']], [$audit['phrases'], $audit['invitations'], $audit['handover_support_words']]);
        $this->assertArrayNotHasKey('held', $audit, 'round 9 §3.2: the audit holds nothing');
        $h->answerCorrectly();
        $this->assertNull(self::lastAttempt($h)['held']);
        $this->assertSame(2, $h->goal('Q06')['count'], 'the attempt counts');
        // The same words earlier in the text, or with no card after them, are still flagged.
        $g = $this->withCheck();
        $this->cardAfterAnswer($g, 'You did that one on your own. Here is the next.');
        $this->assertContains('HELP_CLAIM', array_column(array_column($g->eventsOf('GUARD_FINDING'), 'data'), 'guard'));
    }

    /** Round 8 §3.5, round 9 §3.2: a number before a card equal to the check's answer is logged with where it may come from; from nowhere it is logged too. Nothing holds. */
    public function testTheCheckAnswerBeforeACardIsASignal(): void
    {
        $h = $this->withCheck(); // 3x + 4 = 19, answer 5
        $this->cardAfterAnswer($h, 'Yes, 5 is right.', '2x + 3 = 13', '5'); // the new check's answer is also 5
        $signal = array_values(array_filter($h->eventsOf('GUARD_FINDING'), fn ($e) => $e['data']['guard'] === 'CHECK_CARD_ANSWER_SIGNAL'));
        $this->assertCount(1, $signal);
        $this->assertStringContainsString('the previous problem', $signal[0]['data']['detail']);
        $audits = $h->eventsOf('CHECK_CARD_AUDIT');
        $this->assertTrue(end($audits)['data']['answer_in_text']);
        $h->answerCorrectly();
        $this->assertNull(self::lastAttempt($h)['held']);
        // The same kind of number from nowhere is logged, and holds nothing either.
        $g = $this->withCheck();
        $this->cardAfterAnswer($g, 'Yes. Keep 7 in mind.', '4x + 3 = 31', '7');
        $this->assertContains('CHECK_CARD_ANSWER', array_column(array_column($g->eventsOf('GUARD_FINDING'), 'data'), 'guard'));
        $this->assertSame([], $g->eventsOf('CHECK_HELD'));
        $g->answerCorrectly();
        $this->assertSame([null, 2], [self::lastAttempt($g)['held'], $g->goal('Q06')['count']]);
    }

    /** Round 9 §3.1 and §5.8: a check whose answer appears in it is accepted as posed; nothing is repaired or substituted. */
    public function testAnAnswerInTheQuestionIsAcceptedForACheck(): void
    {
        $h = new Harness();
        $h->start('Q07');
        TutorLoopTest::pose($h, 'practice', 'Q07', 'linear_equation', '3(x + 3) = 18', '3');
        $this->assertStringContainsString('3(x + 3) = 18', $h->view['open_problem']['prompt'], 'a practice problem is accepted whatever its numbers');

        $g = new Harness();
        $g->start('Q07');
        $g->mock->queue('ENVELOPE', ['set' => ['message_type' => 'QUESTION', 'student_message' => 'Here is a check question.', 'item' => [
            'purpose' => 'check', 'skill_id' => 'Q07', 'source' => 'spec', 'recipe_ref' => null,
            'spec' => ['kind' => 'linear_equation', 'expression' => '3(x + 3) = 18'], 'prompt' => 'Solve 3(x + 3) = 18.', 'stated_answer' => '3']]]);
        $calls = $g->mock->calls;
        $g->send('I am ready for a check question');
        $this->assertSame(1, $g->mock->calls - $calls, 'accepted with no repair');
        $this->assertSame([], $g->eventsOf('MODEL_OUTPUT_REJECTED'));
        $this->assertSame([], $g->eventsOf('CHECK_SUBSTITUTED'), 'nothing is substituted');
        $open = $g->open();
        $this->assertSame(['CHECK', 'Q07', 'SPEC'], [$open['purpose'], $open['skill_id'], $open['source']]);
        $this->assertStringContainsString('3(x + 3) = 18', $g->view['open_problem']['prompt']);
        $this->assertSame('ACCEPTED', $g->app->store->db->scalar("SELECT outcome FROM model_calls WHERE call_purpose = 'REPLY' ORDER BY rowid DESC LIMIT 1"));
        $g->answerCorrectly();
        $this->assertSame([1, null], [$g->goal('Q07')['count'], self::lastAttempt($g)['held']], 'it counts like any check');
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

    /**
     * Round 9 §4.3 and §5.3: a help request on an open check gets a HELP reply
     * carrying a practice item in the same envelope. HELP_GIVEN is recorded
     * before the reply is shown, the practice item is shown after the reply,
     * the helped check waits as the original and the next request presents it
     * as practice. When the tutor brings it back, the answer is recorded on
     * that check with help, so it does not count.
     */
    public function testAHelpRequestDuringAnOpenCheckIsHelpedAndRecorded(): void
    {
        $h = $this->withCheck();
        $check = $h->open();
        $calls = $h->mock->calls;
        $before = $h->lines();
        $h->send('can you explain this to me first?');
        $this->assertSame(1, $h->mock->calls - $calls, 'one AI call: the message reaches the tutor');
        $ctx = TutorLoopTest::context($h);
        $this->assertSame(['MESSAGE', 'can you explain this to me first?'], [$ctx['TURN']['kind'], $ctx['TURN']['entry']]);
        $this->assertSame([false, false], [$ctx['ALLOWED']['may_pose_problem'], $ctx['ALLOWED']['may_pose_check']], 'before help: nothing may be posed except a practice item with a help move');
        $new = $h->lastLines($before);
        $this->assertSame([['student', 'message'], ['tutor', 'message'], ['tutor', 'problem']], array_map(fn ($l) => [$l['role'], $l['kind']], $new), 'the reply, then the practice item');
        $help = $h->eventsOf('HELP_GIVEN');
        $this->assertCount(1, $help);
        $this->assertSame(['HELP', 'TUTOR_MOVE', $check['problem_id']], [$help[0]['data']['choice'], $help[0]['data']['source'], $help[0]['data']['problem_id']]);
        $this->assertHelpBeforeTheReply($h);
        $move = $h->eventsOf('AI_MOVE');
        $this->assertSame([true, true], [end($move)['data']['help_on_open_check'], end($move)['data']['with_practice_item']]);
        $this->assertSame(['PRACTICE', 'Q06'], [$h->open()['purpose'], $h->open()['skill_id']], 'the smaller step is the open problem');
        $this->assertNotSame($check['problem_id'], $h->open()['problem_id']);
        $this->assertSame($check['problem_id'], $h->eventsOf('PROBLEM_WAITING')[0]['data']['problem_id'], 'the helped check waits as the original');
        $this->assertSame([], $h->eventsOf('CHECK_HELD'), 'help is recorded, not held');
        // The next request presents the helped problem to the tutor as practice, with its spec and help status.
        $h->answerCorrectly();
        $ctx = TutorLoopTest::context($h);
        $this->assertSame([$check['problem_id'], 'practice', ['HELP'], true], [$ctx['ORIGINAL_PROBLEM']['problem_id'], $ctx['ORIGINAL_PROBLEM']['purpose'],
            $ctx['ORIGINAL_PROBLEM']['help_given'], $ctx['ALLOWED']['may_pose_problem']]);
        $this->assertSame(['kind' => 'linear_equation', 'expression' => '3x + 4 = 19'], $ctx['ORIGINAL_PROBLEM']['spec']);
        $this->assertTrue($ctx['ALLOWED']['may_pose_check'], 'round 10 §4.2: a waiting original no longer blocks a check');
        // The mock brings the original back; it is shown as practice with help, and its answer is recorded on that check with help.
        $this->assertSame([$check['problem_id'], 'practice', 'Practice', true], [$h->open()['problem_id'], $h->view['open_problem']['purpose'],
            $h->view['open_problem']['label'], $h->view['open_problem']['with_help']]);
        $h->answerCorrectly();
        $a = self::lastAttempt($h);
        $this->assertSame([$check['problem_id'], 'CHECK', ['HELP'], null], [$a['problem_id'], $a['purpose'], $a['help'], $a['held']]);
        $this->assertSame(0, $h->goal('Q06')['count'], 'the goal policy applies: the answer does not count');
        $this->assertNull(TutorLoopTest::context($h)['ORIGINAL_PROBLEM']);
    }

    /** Round 9 §3.3 and §5.4: during an open check only HELP and HINT record help; EXPLAIN and ANSWER_QUESTION record none, and the text is logged. */
    public function testOnlyHelpAndHintDuringAnOpenCheckCountAsHelp(): void
    {
        foreach (['HELP', 'HINT'] as $move) {
            $h = $this->withCheck();
            $h->mock->queue('ENVELOPE', ['set' => ['student_message' => 'Think about what is being done to x.', 'item' => null, 'message_type' => 'FEEDBACK',
                'proposed_transition' => ['to_mode' => $move, 'target_skill_id' => 'Q06', 'reason' => 'test', 'evidence_event_ids' => []]]]);
            $h->send('ok');
            $help = $h->eventsOf('HELP_GIVEN');
            $this->assertCount(1, $help, $move);
            $this->assertSame([$move, 'TUTOR_MOVE'], [$help[0]['data']['choice'], $help[0]['data']['source']], $move);
            $this->assertHelpBeforeTheReply($h);
            $this->assertSame('practice', $h->view['open_problem']['purpose'], $move . ': the check becomes practice');
            $h->answerCorrectly();
            $this->assertSame([$move], $h->attempts()[0]['help'], $move);
            $this->assertSame(0, $h->goal('Q06')['count'], $move);
        }
        foreach (['ANSWER_QUESTION', 'EXPLAIN'] as $move) {
            $h = $this->withCheck();
            $text = 'Dividing both sides keeps the equation balanced.';
            $h->mock->queue('ENVELOPE', ['set' => ['student_message' => $text, 'item' => null, 'message_type' => 'FEEDBACK',
                'proposed_transition' => ['to_mode' => $move, 'target_skill_id' => 'Q06', 'reason' => 'test', 'evidence_event_ids' => []]]]);
            $h->send('why do we divide?');
            $this->assertSame([], $h->eventsOf('HELP_GIVEN'), $move . ' records no help');
            $f = $this->loggedAndShown($h, 'MOVE_DURING_CHECK', $text);
            $this->assertSame($move, $f['move']);
            $this->assertSame('check', $h->view['open_problem']['purpose']);
            $h->answerCorrectly();
            $this->assertSame([[], 1], [$h->attempts()[0]['help'], $h->goal('Q06')['count']], $move . ': the answer counts');
        }
        // The same move with no check open records nothing.
        $h = new Harness();
        $h->start('Q06');
        $h->mock->queue('ENVELOPE', ['set' => ['student_message' => 'Think about what is being done to x.',
            'proposed_transition' => ['to_mode' => 'HINT', 'target_skill_id' => 'Q06', 'reason' => 'test', 'evidence_event_ids' => []]]]);
        $h->send('ok');
        $this->assertSame([], $h->eventsOf('HELP_GIVEN'), 'a practice question is not a check');
    }

    /** Round 9 §3.2: text during an open check that uses its numbers, expression or answer with no help move is logged as a signal; nothing is held. */
    public function testTheBackstopDuringAnOpenCheckIsASignal(): void
    {
        $h = $this->withCheck();
        $text = 'What could you do to both sides to deal with the 4?';
        $h->mock->queue('ENVELOPE', ['set' => ['student_message' => $text, 'item' => null, 'message_type' => 'FEEDBACK',
            'proposed_transition' => ['to_mode' => 'CONTINUE', 'target_skill_id' => 'Q06', 'reason' => 'test', 'evidence_event_ids' => []]]]);
        $h->send('can I have a clue');
        $f = $this->loggedAndShown($h, 'DURING_CHECK', $text);
        $this->assertSame([['numbers'], ['4'], true], [$f['causes'], $f['numbers'], $f['signal']]);
        $this->assertSame([[], []], [$h->eventsOf('CHECK_HELD'), $h->eventsOf('HELP_GIVEN')]);
        $h->answerCorrectly();
        $this->assertSame([null, 1], [$h->attempts()[0]['held'], $h->goal('Q06')['count']], 'nothing is held; the answer counts');
        $g = $this->withCheck();
        $g->mock->queue('ENVELOPE', ['set' => ['student_message' => 'Look again at 3x + 4 = 19.', 'item' => null, 'message_type' => 'FEEDBACK',
            'proposed_transition' => ['to_mode' => 'CONTINUE', 'target_skill_id' => 'Q06', 'reason' => 'test', 'evidence_event_ids' => []]]]);
        $g->send('hmm');
        $this->assertSame(['expression', 'numbers'], $this->loggedAndShown($g, 'DURING_CHECK', 'Look again at 3x + 4 = 19.')['causes']);
        // A refusal with no numbers records nothing.
        $r = $this->withCheck();
        $r->send('just tell me the answer');
        $this->assertSame('I won\'t give you the answer to this one, but I can help you take a first step.', end($r->view['conversation'])['text']);
        $this->assertSame([], array_filter($r->eventsOf('GUARD_FINDING'), fn ($e) => $e['data']['guard'] === 'DURING_CHECK'));
    }

    private function assertHelpBeforeTheReply(Harness $h): void
    {
        $types = array_column($h->events(), 'type');
        $help = max(array_keys($types, 'HELP_GIVEN', true));
        $message = max(array_keys($types, 'TUTOR_MESSAGE', true));
        $this->assertLessThan($message, $help, 'HELP_GIVEN is recorded before the reply is shown');
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

    /**
     * Round 7 §6.3, kept in round 8: code never classifies typed text as
     * stuck. "idk" during a check is a message; help is recorded only when the
     * tutor's reply marks its move as help (here the mock does), never from
     * the typed words.
     */
    public function testTypedIdkDuringACheckIsAMessageNotCodeClassifiedHelp(): void
    {
        $h = $this->withCheck();
        $h->send('idk');
        $this->assertSame('MESSAGE', TutorLoopTest::context($h)['TURN']['kind']);
        $this->assertSame([], $h->eventsOf('TYPED_STUCK'));
        $this->assertSame([['HELP', 'TUTOR_MOVE']], array_map(fn ($e) => [$e['data']['choice'], $e['data']['source']], $h->eventsOf('HELP_GIVEN')), 'recorded from the tutor\'s move');
        $g = $this->withCheck();
        $g->mock->queue('ENVELOPE', ['set' => ['student_message' => 'Have a careful look at the question.', 'item' => null, 'message_type' => 'FEEDBACK',
            'proposed_transition' => ['to_mode' => 'CONTINUE', 'target_skill_id' => 'Q06', 'reason' => 'test', 'evidence_event_ids' => []]]]);
        $g->send('idk');
        $this->assertSame([], $g->eventsOf('HELP_GIVEN'), 'no help move, no help');
        $g->send('???');
        $this->assertSame([], $g->eventsOf('HELP_GIVEN'));
        $g->answerCorrectly();
        $a = $g->attempts()[0];
        $this->assertSame(['CORRECT', []], [$a['verdict'], $a['help']]);
        $this->assertSame(1, $g->goal('Q06')['count']);
    }

    /** Round 7 §6.4: "Explain the idea" is gone from "I'm stuck". */
    public function testExplainTheIdeaIsNoLongerAChoice(): void
    {
        $h = $this->withCheck();
        $this->assertSame(400, $h->stuck('EXPLAIN')->status);
        $this->assertSame([], $h->eventsOf('HELP_GIVEN'));
        $this->assertSame(['HINT', 'NOT_LEARNED'], array_keys(TutorEngine::STUCK_LABELS));
    }

    /** Round 9 §3.2: the AI's text during an open check that contains its answer, with no help move, is logged; nothing is held. */
    public function testAnAnswerLeakDuringAnOpenCheckIsLogged(): void
    {
        $h = $this->withCheck();
        $h->mock->queue('LEAK');
        $h->send('just tell me the answer');
        $this->loggedAndShown($h, 'ANSWER_LEAK', 'The answer is 5.');
        $this->assertSame(['answer'], $this->loggedAndShown($h, 'DURING_CHECK', 'The answer is 5.')['causes']);
        $this->assertSame([], $h->eventsOf('CHECK_HELD'));
        $h->answerCorrectly();
        $this->assertNull($h->attempts()[0]['held']);
        $this->assertSame(1, $h->goal('Q06')['count'], 'no detector changes a count');
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

    /** A problem posed during an open check without help is rejected; with help recorded (round 9 §4.3) the tutor may pose practice. */
    public function testTheAiPosesAProblemWhileACheckIsOpen(): void
    {
        $h = $this->withCheck();
        $check = $h->view['open_problem']['problem_id'];
        $h->mock->queue('POSE_ANYWAY');
        $h->send('ok');
        $this->rejectedWith($h, 'no problem may be posed this turn');
        $this->assertSame($check, $h->view['open_problem']['problem_id'], 'the check question is still the open problem');
        $this->assertSame('check', $h->view['open_problem']['purpose']);
        // After "I'm stuck", help is recorded and ALLOWED.may_pose_problem is true.
        $g = $this->withCheck();
        $g->mock->queue('POSE_ANYWAY');
        $g->stuck('HINT');
        $this->assertTrue(TutorLoopTest::context($g)['ALLOWED']['may_pose_problem']);
        $this->assertSame([], $g->eventsOf('MODEL_OUTPUT_REJECTED'));
        $this->assertSame(['PRACTICE', 'N18'], [$g->open()['purpose'], $g->open()['skill_id']]);
    }
}
