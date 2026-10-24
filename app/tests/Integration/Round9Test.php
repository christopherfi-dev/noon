<?php
declare(strict_types=1);

namespace AlgebraEngine\Tests\Integration;

use AlgebraEngine\Checking\EquationStepCheck;
use AlgebraEngine\Items\ItemFactory;
use AlgebraEngine\Provider\DisclosureGuard;
use AlgebraEngine\Tests\Support\Harness;
use AlgebraEngine\Tutor\AnswerCheck;
use AlgebraEngine\Tutor\Counters;
use AlgebraEngine\Tutor\EntryReader;
use AlgebraEngine\Tutor\Gradebook;
use AlgebraEngine\Tutor\Numbers;
use AlgebraEngine\Tutor\ProblemFactory;
use AlgebraEngine\Tutor\ProblemVerifier;
use AlgebraEngine\Tutor\TextGuard;
use AlgebraEngine\Tutor\TutorEngine;
use PHPUnit\Framework\TestCase;

/**
 * Round 9 §5: the targeted offline tests, one group per case (1 to 9), and
 * the §4.6 detector cases. Mock tutor throughout: none of this is evidence of
 * live-provider behaviour or of learning.
 */
final class Round9Test extends TestCase
{
    // ------------------------------------------------------------ helpers

    private function rejectedWith(Harness $h, string $needle): void
    {
        $errors = [];
        foreach ($h->eventsOf('MODEL_OUTPUT_REJECTED') as $e) {
            $errors = array_merge($errors, $e['data']['errors']);
        }
        $this->assertNotSame([], array_filter($errors, fn ($e) => str_contains($e, $needle)), 'expected a rejection containing "' . $needle . '"; got ' . json_encode($errors));
    }

    private static function lastAttempt(Harness $h): array
    {
        $a = $h->attempts();
        return $a[count($a) - 1];
    }

    private function withCheck(string $expr = '3x + 4 = 19', string $answer = '5'): Harness
    {
        $h = new Harness();
        $h->start('Q06');
        TutorLoopTest::pose($h, 'check', 'Q06', 'linear_equation', $expr, $answer, '', 'Here is a check question.');
        return $h;
    }

    /** Queue a check item for the next reply (the reply to the student's answer poses it). */
    private static function queueCheck(Harness $h, string $skill, string $expr, string $stated): void
    {
        $h->mock->queue('ENVELOPE', ['set' => ['message_type' => 'QUESTION', 'student_message' => 'Here is a check question.', 'item' => [
            'purpose' => 'check', 'skill_id' => $skill, 'source' => 'spec', 'recipe_ref' => null,
            'spec' => ['kind' => 'linear_equation', 'expression' => $expr], 'prompt' => '', 'stated_answer' => $stated]]]);
    }

    /** Queue a reply that poses nothing. */
    private static function queueFeedback(Harness $h, string $text = 'Thanks.'): void
    {
        $h->mock->queue('ENVELOPE', ['set' => ['message_type' => 'FEEDBACK', 'student_message' => $text, 'item' => null, 'proposed_transition' => null]]);
    }

    /** Pose an equation_step practice problem through the loop. */
    private static function poseStep(Harness $h, string $expr, string $operation, int|string $operand, string $stated, string $skill = 'Q06'): void
    {
        $h->mock->queue('ENVELOPE', ['set' => ['message_type' => 'QUESTION', 'student_message' => 'Here is one step to try.', 'item' => [
            'purpose' => 'practice', 'skill_id' => $skill, 'source' => 'spec', 'recipe_ref' => null,
            'spec' => ['kind' => 'equation_step', 'expression' => $expr, 'operation' => $operation, 'operand' => $operand], 'prompt' => '', 'stated_answer' => $stated,
        ], 'proposed_transition' => ['to_mode' => 'POSE_PROBLEM', 'target_skill_id' => $skill, 'reason' => 'test', 'evidence_event_ids' => []]]]);
        $h->send('next one please');
    }

    private function tutorLines(Harness $h): array
    {
        return array_values(array_filter($h->lines(), fn ($l) => $l['role'] === 'tutor' && $l['kind'] === 'message'));
    }

    // ------------------------------------------- 1. working read as an answer

    /** §5.1: "I added 9" with a problem open is not graded, and the problem stays open. */
    public function testWorkingIsNotReadAsAnAnswer(): void
    {
        $h = $this->withCheck();
        $open = $h->open();
        $calls = $h->mock->calls;
        $this->assertSame(200, $h->send('I added 9')->status);
        $reading = TutorLoopTest::context($h, 2);
        $this->assertSame(['READING', 'I added 9'], [$reading['TURN']['kind'], $reading['TURN']['entry']], 'the AI was asked for a reading');
        $this->assertSame(2, $h->mock->calls - $calls, 'one reading call, then the reply');
        $this->assertSame([], $h->attempts(), 'not graded');
        $this->assertSame('MESSAGE', TutorLoopTest::context($h)['TURN']['kind'], 'the tutor replies to it as a message');
        $this->assertSame([$open['problem_id'], 'check'], [$h->open()['problem_id'], $h->view['open_problem']['purpose']], 'the problem stays open');
        $h->answerCorrectly();
        $this->assertSame(['CORRECT', 1], [self::lastAttempt($h)['verdict'], $h->goal('Q06')['count']]);
    }

    // ------------------------------------------- 2. an answer with a question

    /** §5.2: "My answer is 10. Why does dividing by 5 work?" is graded as 10. */
    public function testAnAnswerWithAQuestionIsGraded(): void
    {
        $h = new Harness();
        $h->start('Q05');
        TutorLoopTest::pose($h, 'practice', 'Q05', 'linear_equation', '5x = 50', '10');
        $h->send('My answer is 10. Why does dividing by 5 work?');
        $a = $h->attempts();
        $this->assertCount(1, $a);
        $this->assertSame(['10', EntryReader::NOTE_AI_READING, 'CORRECT', 'My answer is 10. Why does dividing by 5 work?'], [$a[0]['read_as'], $a[0]['reading_note'], $a[0]['verdict'], $a[0]['entry']]);
        $ctx = TutorLoopTest::context($h);
        $this->assertSame(['ANSWER', 'My answer is 10. Why does dividing by 5 work?'], [$ctx['TURN']['kind'], $ctx['TURN']['entry']], 'the tutor sees the whole entry, question included');
    }

    // --------------------------------------- 3. help and a smaller step together

    /** §5.3: a HINT reply during a check carries a practice item; help is recorded before the reply, and the next request presents the helped problem as practice. */
    public function testHelpAndASmallerStepTogether(): void
    {
        $h = $this->withCheck();
        $check = $h->open();
        $h->mock->queue('ENVELOPE', ['set' => ['message_type' => 'QUESTION', 'student_message' => 'Try this smaller one first.', 'item' => [
            'purpose' => 'practice', 'skill_id' => 'Q06', 'source' => 'spec', 'recipe_ref' => null,
            'spec' => ['kind' => 'equation_step', 'expression' => '3x + 4 = 19', 'operation' => 'subtract', 'operand' => 4], 'prompt' => '', 'stated_answer' => '3x = 15',
        ], 'proposed_transition' => ['to_mode' => 'HINT', 'target_skill_id' => 'Q06', 'reason' => 'test', 'evidence_event_ids' => []]]]);
        $before = $h->lines();
        $h->send('where do I start?');
        $this->assertSame([], $h->eventsOf('MODEL_OUTPUT_REJECTED'), 'accepted in the same envelope');
        $types = array_column($h->events(), 'type');
        $this->assertLessThan(max(array_keys($types, 'TUTOR_MESSAGE', true)), max(array_keys($types, 'HELP_GIVEN', true)), 'help recorded before the reply');
        $this->assertLessThan(max(array_keys($types, 'PROBLEM_PRESENTED', true)), max(array_keys($types, 'TUTOR_MESSAGE', true)), 'the practice item after the reply');
        $this->assertSame([['student', 'message'], ['tutor', 'message'], ['tutor', 'problem']], array_map(fn ($l) => [$l['role'], $l['kind']], $h->lastLines($before)));
        $this->assertSame(['HINT', $check['problem_id']], [$h->eventsOf('HELP_GIVEN')[0]['data']['choice'], $h->eventsOf('HELP_GIVEN')[0]['data']['problem_id']]);
        $this->assertSame('Subtract 4 from both sides of 3x + 4 = 19. Write the equation you get.', $h->view['open_problem']['prompt']);
        // The next request presents the helped problem as practice.
        $h->send('3x = 15');
        $ctx = TutorLoopTest::context($h);
        $this->assertSame([$check['problem_id'], 'practice', ['HINT']], [$ctx['ORIGINAL_PROBLEM']['problem_id'], $ctx['ORIGINAL_PROBLEM']['purpose'], $ctx['ORIGINAL_PROBLEM']['help_given']]);
        $this->assertTrue($ctx['ALLOWED']['may_pose_problem']);
        // With a help move, the open check itself is presented as practice in OPEN_PROBLEM.
        $g = $this->withCheck();
        $g->mock->queue('ENVELOPE', ['set' => ['student_message' => 'Think about what is being done to x.', 'item' => null, 'message_type' => 'FEEDBACK',
            'proposed_transition' => ['to_mode' => 'HELP', 'target_skill_id' => 'Q06', 'reason' => 'test', 'evidence_event_ids' => []]]]);
        $g->send('ok');
        $g->send('what next?');
        $ctx = TutorLoopTest::context($g);
        $this->assertSame(['practice', ['HELP'], true, false], [$ctx['OPEN_PROBLEM']['purpose'], $ctx['OPEN_PROBLEM']['help_given'], $ctx['ALLOWED']['may_pose_problem'], $ctx['ALLOWED']['may_pose_check']]);
        // Help recorded on a check held from a verdict conflict also restarts the run.
        $v = $this->withCheck();
        self::queueCheck($v, 'Q06', '4x + 3 = 31', '7');
        $v->answerCorrectly();
        $this->assertSame(1, $v->goal('Q06')['count']);
        $v->stuck('HINT');
        $v->mock->queue('VERDICT_CONFLICT');
        $v->answerCorrectly();
        $a = self::lastAttempt($v);
        $this->assertSame([2, 'VERDICT_CONFLICT', ['HINT']], [count($v->attempts()), $a['held'], $a['help']]);
        $this->assertSame(0, $v->goal('Q06')['count'], 'help on a held check restarts the run');
        $w = $this->withCheck();
        self::queueCheck($w, 'Q06', '4x + 3 = 31', '7');
        $w->answerCorrectly();
        $w->mock->queue('VERDICT_CONFLICT');
        $w->answerCorrectly();
        $this->assertSame([1, 'VERDICT_CONFLICT', []], [$w->goal('Q06')['count'], self::lastAttempt($w)['held'], self::lastAttempt($w)['help']], 'held without help: neither way');
    }

    // ------------------------------------------- 4. ANSWER_QUESTION used for help

    /** §5.4: an ANSWER_QUESTION reply during a check records no help, and its text is logged. */
    public function testAnswerQuestionDuringACheckRecordsNoHelp(): void
    {
        $h = $this->withCheck();
        $text = 'You can undo the 4 first by subtracting it from both sides, then divide by 3.';
        $h->mock->queue('ENVELOPE', ['set' => ['student_message' => $text, 'item' => null, 'message_type' => 'FEEDBACK',
            'proposed_transition' => ['to_mode' => 'ANSWER_QUESTION', 'target_skill_id' => 'Q06', 'reason' => 'test', 'evidence_event_ids' => []]]]);
        $h->send('how do I do this one?');
        $this->assertSame([], $h->eventsOf('HELP_GIVEN'));
        $found = array_column(array_column($h->eventsOf('GUARD_FINDING'), 'data'), null, 'guard');
        $this->assertSame([$text, 'ANSWER_QUESTION', true], [$found['MOVE_DURING_CHECK']['text'], $found['MOVE_DURING_CHECK']['move'], $found['MOVE_DURING_CHECK']['signal']]);
        $this->assertSame([$text, ['numbers']], [$found['DURING_CHECK']['text'], $found['DURING_CHECK']['causes']], 'its text is logged');
        $lines = $this->tutorLines($h);
        $this->assertSame($text, end($lines)['text'], 'shown unchanged');
        $this->assertSame([[], 'check'], [$h->eventsOf('CHECK_HELD'), $h->view['open_problem']['purpose']]);
        $moves = $h->eventsOf('AI_MOVE');
        $this->assertFalse(end($moves)['data']['help_on_open_check']);
        $h->answerCorrectly();
        $this->assertSame([[], null, 1], [self::lastAttempt($h)['help'], self::lastAttempt($h)['held'], $h->goal('Q06')['count']]);
        $this->assertSame(['HELP', 'HINT'], TutorEngine::HELP_MOVES);
    }

    // ------------------------------------------------------------ 5. stale reply

    /** §5.5 and §4.2: a reply whose request's state revision changed while the call was in flight is discarded before anything is committed. */
    public function testAStaleReplyIsDiscarded(): void
    {
        $h = $this->withCheck();
        $rev = $h->view['revision'];
        $events = count($h->events());
        $h->mock->onCall = function () use ($h) {
            $h->app->store->db->exec('UPDATE sessions SET revision = revision + 1 WHERE session_id = ?', [$h->sessionId()]);
        };
        $r = $h->send('can you help me?');
        $this->assertSame([409, 'OPERATION_CANCELLED'], [$r->status, $r->body['error_code']]);
        $this->assertSame($events, count($h->events()), 'nothing from the stale reply is committed');
        $this->assertSame([[], []], [$h->eventsOf('HELP_GIVEN'), $h->eventsOf('PROBLEM_WAITING')], 'its help move is not recorded');
        $this->assertSame($rev + 1, $h->row()['revision'], 'only the other writer advanced the revision');
        $this->assertSame('DISCARDED', $h->app->store->db->one('SELECT status FROM operations ORDER BY rowid DESC LIMIT 1')['status']);
        // Identity fields in a reply are ignored; code attaches its own (owner's decision).
        $g = $this->withCheck();
        $g->mock->queue('ENVELOPE', ['set' => ['session_id' => 'ses_someone_else', 'state_revision' => 999, 'reply_to_event_id' => 'evt_other', 'student_message' => 'Keep going.',
            'item' => null, 'message_type' => 'FEEDBACK']]);
        $this->assertSame(200, $g->send('ok')->status);
        $this->assertSame([], $g->eventsOf('MODEL_OUTPUT_REJECTED'), 'no repair');
        $lines = $this->tutorLines($g);
        $this->assertSame('Keep going.', end($lines)['text']);
        $this->assertNotSame(999, $g->row()['revision']);
    }

    // ------------------------------------------------------- 6. slip eligibility

    /** §5.6 and §4.4: every slip case. */
    public function testSlipEligibility(): void
    {
        $problems = new ProblemFactory(new ItemFactory());
        $checker = new AnswerCheck(new ItemFactory());
        $slip = fn (array $p, string $entry) => $checker->check($p, $entry)['slip'];
        $e7 = $problems->fromRecipe('POSITIVE_DISTRIBUTION', ['a' => 7, 'b' => 5], 'CHECK');
        $this->assertSame('The other terms match; the constant 25 is a near miss of 7 × 5 = 35.', $slip($e7, '7x + 25')['detail']);
        $this->assertSame('The other terms match; the constant 30 is a near miss of 7 × 5 = 35.', $slip($e7, '7x + 30')['detail']);
        foreach (['7x + 95', '7x + 12', '7x - 35'] as $entry) {
            $this->assertNull($slip($e7, $entry), $entry . ' is not eligible');
        }
        $this->assertNull($slip($problems->fromRecipe('POSITIVE_DISTRIBUTION', ['a' => 2, 'b' => 3], 'CHECK'), '2x + 5'), '2x + 5 for 2(x + 3) is not eligible');
        $this->assertNull($slip($e7, '6x + 25'), 'only the constant may differ');
        $t = $problems->fromRecipe('DISTRIBUTION_TRANSFER', ['form' => 'COEFF_INSIDE', 'a' => 4, 'p' => 3, 'q' => 6], 'CHECK'); // 12x + 24
        $this->assertSame('The other terms match; the constant 28 is a near miss of 4 × 6 = 24.', $slip($t, '12x + 28')['detail']);
        $this->assertNull($slip($t, '12x + 10'), '4 + 6');
        $this->assertNull($slip($t, '11x + 24'));
        // Linear equations: a bare value of x is never eligible; the signatures stay observations.
        foreach ([['GROUPED_LINEAR', ['a' => 3, 'b' => 4, 'c' => 27], '6'], ['GROUPED_LINEAR', ['a' => 3, 'b' => 4, 'c' => 27], '4'],
            ['GROUPED_LINEAR_TRANSFER', ['form' => 'PLUS_D', 'a' => 2, 'b' => 3, 'c' => 20, 'd' => 4], '6'], ['TWO_STEP', ['a' => 3, 'b' => 4, 'c' => 19], '4']] as [$tpl, $params, $entry]) {
            $p = $problems->fromRecipe($tpl, $params, 'CHECK');
            $r = $checker->check($p, $entry);
            $this->assertNull($r['slip'], $tpl . ' ' . $entry);
            $this->assertNotSame([], array_intersect(['ARITH_FINAL_PLUS', 'ARITH_FINAL_MINUS', 'ARITH_OFF_BY_ONE'], array_column($r['signatures'], 'code')), $tpl . ': still observed');
            $this->assertSame('The answer ' . $entry . ' differs from the solution by 1.', $r['signatures'][0]['label']);
        }
        // Signed arithmetic magnitude slips keep their rule.
        $n05 = $problems->fromRecipe('SIGNED_MULTIPLY', ['a' => -6, 'b' => 7], 'CHECK');
        $this->assertSame('The sign matches; the size is a near miss of 6 × 7 = 42.', $slip($n05, '-48')['detail']);
        $this->assertNull($slip($n05, '-40'));
        // Through the loop: an off-by-one on a Q07 check resets the run, and SLIP.eligible_for_pause is false.
        $h = new Harness();
        $h->start('Q07');
        TutorLoopTest::pose($h, 'check', 'Q07', 'linear_equation', '3(x + 4) = 27', '5', '', 'Here is a check question.');
        self::queueCheck($h, 'Q07', '2(x + 3) = 20', '7');
        $h->answerCorrectly();
        $this->assertSame([1, 'Solve 2(x + 3) = 20 for x.'], [$h->goal('Q07')['count'], $h->view['open_problem']['prompt']]);
        $h->send('8');
        $ctx = TutorLoopTest::context($h);
        $this->assertFalse($ctx['SLIP']['eligible_for_pause']);
        $this->assertNull($ctx['CODE_RESULT']['slip']);
        $this->assertSame(['ARITH_FINAL_PLUS', 'The answer 8 differs from the solution by 1.'], [$ctx['CODE_RESULT']['signatures'][0]['code'], $ctx['CODE_RESULT']['signatures'][0]['observation']]);
        $this->assertSame([0, false], [$h->goal('Q07')['count'], $h->goal('Q07')['paused']], 'not paused: the run resets');
    }

    // ------------------------------------------------------ 7. equation_step

    /** §5.7 and §4.5: every case, from 3x + 6 = 15 with "subtract 6". */
    public function testEquationStep(): void
    {
        $answer = ['kind' => 'equation_step', 'expected' => '3*x = 9', 'start' => '3*x + 6 = 15'];
        foreach (['3x = 9' => 'STEP_CORRECT', '9 = 3x' => 'STEP_CORRECT', '3x + 6 − 6 = 15 − 6' => 'STEP_CORRECT', '3x + 6 = 15' => 'EQUIVALENT_NOT_STEP',
            '6x + 12 = 30' => 'EQUIVALENT_NOT_STEP', 'x = 3' => 'EQUIVALENT_NOT_STEP', '3x = 21' => 'NOT_EQUIVALENT'] as $entry => $class) {
            $o = EquationStepCheck::check($answer, $entry);
            $this->assertSame($class, EquationStepCheck::verdictClass($o), $entry);
            $this->assertSame($class === 'STEP_CORRECT' ? 'CORRECT' : 'INCORRECT', $o->verdict, $entry);
        }
        $this->assertSame('UNSCORABLE', EquationStepCheck::check($answer, '9')->verdict);

        // Through the loop: code renders the wording, checks each entry, and asks for a rewrite of a non-equation.
        $h = new Harness();
        $h->start('Q06');
        foreach (['3x = 9' => ['CORRECT', 'STEP_CORRECT'], '9 = 3x' => ['CORRECT', 'STEP_CORRECT'], '3x + 6 − 6 = 15 − 6' => ['CORRECT', 'STEP_CORRECT'],
            '3x + 6 = 15' => ['INCORRECT', 'EQUIVALENT_NOT_STEP'], '6x + 12 = 30' => ['INCORRECT', 'EQUIVALENT_NOT_STEP'], 'x = 3' => ['INCORRECT', 'EQUIVALENT_NOT_STEP'],
            '3x = 21' => ['INCORRECT', 'NOT_EQUIVALENT']] as $entry => [$verdict, $class]) {
            self::poseStep($h, '3x + 6 = 15', 'subtract', 6, '3x = 9');
            $this->assertSame(['practice', 'Subtract 6 from both sides of 3x + 6 = 15. Write the equation you get.'], [$h->view['open_problem']['purpose'], $h->view['open_problem']['prompt']]);
            self::queueFeedback($h);
            $h->send($entry);
            $a = self::lastAttempt($h);
            $this->assertSame([$entry, $verdict], [$a['entry'], $a['verdict']], $entry);
            $recorded = $h->eventsOf('ATTEMPT_RECORDED');
            $this->assertSame($class, end($recorded)['data']['step_verdict'], $entry);
            if ($class === 'EQUIVALENT_NOT_STEP') {
                $ctx = TutorLoopTest::context($h);
                $this->assertSame(['EQUIVALENT_NOT_STEP', AnswerCheck::EQUIVALENT_NOT_STEP_LABEL], [$ctx['CODE_RESULT']['signatures'][0]['code'], $ctx['CODE_RESULT']['signatures'][0]['observation']]);
            }
        }
        $count = count($h->attempts());
        self::poseStep($h, '3x + 6 = 15', 'subtract', 6, '3x = 9');
        $h->send('9');
        $this->assertCount($count, $h->attempts(), 'a non-equation is a rewrite request, never a wrong answer');
        $this->assertCount(1, $h->eventsOf('ENTRY_NOT_AN_EQUATION'));
        $ctx = TutorLoopTest::context($h);
        $this->assertSame(['ENTRY_NOT_READ', [TutorEngine::NOT_EQUATION_REASON]], [$ctx['TURN']['kind'], $ctx['TURN']['not_read_because']]);
        $this->assertSame('practice', $h->view['open_problem']['purpose'], 'the problem stays open');
        // The other operations' wording.
        foreach ([['add', 4, '3x - 4 = 11', '3x = 15', 'Add 4 to both sides of 3x − 4 = 11. Write the equation you get.'],
            ['multiply', 3, 'x/3 = 4', 'x = 12', 'Multiply both sides of x ÷ 3 = 4 by 3. Write the equation you get.'],
            ['divide', 3, '3x = 12', 'x = 4', 'Divide both sides of 3x = 12 by 3. Write the equation you get.']] as [$op, $k, $expr, $stated, $wording]) {
            self::poseStep($h, $expr, $op, $k, $stated);
            $this->assertSame($wording, $h->view['open_problem']['prompt'], $op);
        }
        // The verifier: a stated answer that differs, a zero operand for multiply or divide, and a check purpose are rejected.
        $g = new Harness();
        $g->start('Q06');
        $g->mock->queue('ENVELOPE', ['set' => ['message_type' => 'QUESTION', 'student_message' => 'Try this.', 'item' => [
            'purpose' => 'practice', 'skill_id' => 'Q06', 'source' => 'spec', 'recipe_ref' => null,
            'spec' => ['kind' => 'equation_step', 'expression' => '3x + 6 = 15', 'operation' => 'subtract', 'operand' => 6], 'prompt' => '', 'stated_answer' => '3x = 21']]]);
        $g->send('next one please');
        $this->rejectedWith($g, 'differs from the equation code computed from the spec');
        $v = new ProblemVerifier(new ProblemFactory(new ItemFactory()), new \AlgebraEngine\Tutor\PatternMatcher(new ItemFactory()));
        $item = fn (string $purpose, string $op, $k, string $stated) => ['purpose' => $purpose, 'skill_id' => 'Q06', 'source' => 'spec', 'recipe_ref' => null,
            'spec' => ['kind' => 'equation_step', 'expression' => '3x + 6 = 15', 'operation' => $op, 'operand' => $k], 'prompt' => '', 'stated_answer' => $stated];
        $ctx = ['recipes' => [], 'used_fingerprints' => [], 'retry_target' => null, 'limits' => [], 'used_number_sets' => []];
        $this->assertTrue($v->verify($item('practice', 'subtract', 6, '3x = 9'), $ctx)['ok']);
        foreach ([['multiply', 0], ['divide', 0], ['divide', '0']] as [$op, $k]) {
            $r = $v->verify($item('practice', $op, $k, '0 = 0'), $ctx);
            $this->assertFalse($r['ok'], $op . ' by zero');
        }
        $r = $v->verify($item('check', 'subtract', 6, '3x = 9'), $ctx);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('an equation_step problem has purpose practice', implode('; ', $r['errors']));
    }

    // ------------------------------------------------------ 8. removed rules

    /** §5.8: a check whose answer appears in it is accepted; nothing is substituted; no detector holds anything. */
    public function testRemovedRules(): void
    {
        $h = new Harness();
        $h->start('Q07');
        TutorLoopTest::pose($h, 'check', 'Q07', 'linear_equation', '3(x + 3) = 18', '3', '', 'Here is a check question.');
        $this->assertSame([[], []], [$h->eventsOf('MODEL_OUTPUT_REJECTED'), $h->eventsOf('CHECK_SUBSTITUTED')]);
        $this->assertSame('SPEC', $h->open()['source']);
        $this->assertFalse(defined(ProblemVerifier::class . '::ANSWER_IN_QUESTION'));
        $h->answerCorrectly();
        $this->assertSame(1, $h->goal('Q07')['count']);

        // Every text detector fires during one session; nothing is held and every check counts.
        $g = $this->withCheck();
        $card = fn (string $msg, string $expr, string $stated) => $g->mock->queue('ENVELOPE', ['set' => ['message_type' => 'QUESTION', 'student_message' => $msg, 'item' => [
            'purpose' => 'check', 'skill_id' => 'Q06', 'source' => 'spec', 'recipe_ref' => null, 'spec' => ['kind' => 'linear_equation', 'expression' => $expr], 'prompt' => '', 'stated_answer' => $stated]]]);
        $card('You did that on your own. Remember to undo the addition first; the answer is 7. Your Q06 goal is met, you have mastered it, and the host checked it.', '4x + 3 = 31', '7');
        $g->answerCorrectly();
        self::queueCheck($g, 'Q06', '2x + 5 = 17', '6');
        $g->answerCorrectly();
        $g->mock->queue('LEAK');
        $g->send('just tell me');
        $g->answerCorrectly();
        $guards = array_unique(array_column(array_column($g->eventsOf('GUARD_FINDING'), 'data'), 'guard'));
        foreach (['CHECK_CARD_PHRASE', 'CHECK_CARD_ANSWER', 'HELP_CLAIM', 'MASTERY_OR_GOAL_CLAIM', 'INTERNAL_VOCABULARY', 'SKILL_CODE', 'ANSWER_LEAK', 'DURING_CHECK'] as $guard) {
            $this->assertContains($guard, $guards, $guard . ' fired');
        }
        foreach ($g->eventsOf('GUARD_FINDING') as $e) {
            $this->assertTrue($e['data']['signal']);
        }
        $this->assertSame([[], []], [$g->eventsOf('CHECK_HELD'), $g->eventsOf('ATTEMPT_HELD')], 'no detector holds anything');
        $this->assertSame([null, null, null], array_column($g->attempts(), 'held'));
        $this->assertSame(3, $g->goal('Q06')['count'], 'no detector changes a count');
    }

    // ---------------------------------------------------------- 9. corrections

    /** Three Q06 checks answered correctly; the goal is met. */
    private function metQ06(): Harness
    {
        $h = $this->withCheck();
        foreach ([['4x + 3 = 31', '7'], ['2x + 5 = 17', '6']] as [$expr, $answer]) {
            self::queueCheck($h, 'Q06', $expr, $answer);
            $h->answerCorrectly();
        }
        self::queueFeedback($h);
        $h->answerCorrectly();
        $this->assertSame(['Solve 3x + 4 = 19 for x.', 'Solve 4x + 3 = 31 for x.', 'Solve 2x + 5 = 17 for x.'], array_column($h->attempts(), 'prompt'));
        $this->assertTrue($h->goal('Q06')['met']);
        $this->assertCount(1, $h->eventsOf('GOAL_MET'));
        return $h;
    }

    /** §4.7 test 1: a counted check corrected to "with help" stops counting, and its goal is recomputed. */
    public function testACountedCheckCorrectedToWithHelpStopsCounting(): void
    {
        $h = $this->metQ06();
        $second = $h->attempts()[1];
        $rev = $h->row()['revision'];
        $r = $h->app->engine->applyCorrection($h->sessionId(), $second['attempt_id'], 'help', 'with_help', 'The teacher sat with the student on this one.');
        $this->assertSame($rev + 1, $r['revision']);
        $this->assertSame('without_help', $r['previous']);
        $this->assertSame([1, false], [$h->goal('Q06')['count'], $h->goal('Q06')['met']], 'the run restarts after the corrected attempt');
        $this->assertCount(1, $h->eventsOf('GOAL_SUSPENDED'), 'the earlier GOAL_MET stays; a suspension is recorded');
        $this->assertSame($rev + 1, $h->row()['revision']);
        $snap = $h->row()['snapshot'];
        $this->assertSame([1, false], [$snap['goals'][0]['count'] ?? null, $snap['goals'][0]['met'] ?? null]);
        $this->assertSame(['CORRECTION'], $h->attempts()[1]['help']);
        // The report and the next request use the corrected record.
        $teacher = $h->request('GET', 'report')->body['summary']['teacher'];
        $this->assertSame([2, 1], [$teacher['skills'][0]['independent'], $teacher['skills'][0]['assisted']]);
        // The correction advanced the revision: an open page is out of date and reloads.
        $this->assertSame('STALE_REVISION', $h->send('next one please')->body['error_code']);
        $h->view['revision'] = $h->row()['revision'];
        $this->assertSame(200, $h->send('next one please')->status);
        $ctx = TutorLoopTest::context($h);
        $q06 = array_values(array_filter($ctx['GOALS'], fn ($g) => $g['skill_id'] === 'Q06'))[0];
        $this->assertSame([1, false], [$q06['correct_in_a_row'], $q06['met']]);
    }

    /** §4.7 test 2: a corrected or suspended verdict updates the counts and the report that depend on it. */
    public function testACorrectedOrSuspendedVerdictUpdatesCountsAndReport(): void
    {
        // A verdict corrected on a counted check: the run restarts, the report follows.
        $h = $this->metQ06();
        $third = $h->attempts()[2];
        $h->event('END_SESSION', [], null, null, false);
        $before = $h->app->store->latestReport($h->sessionId());
        $this->assertStringContainsString('(Q06) — goal met', implode(' ', array_column($before['summary']['teacher']['sections'], 'lines', 'heading')['Goals met']));
        $r = $h->app->engine->applyCorrection($h->sessionId(), $third['attempt_id'], 'verdict', 'INCORRECT', 'The student said they guessed; the teacher marks it not yet.');
        $this->assertTrue($r['report_rebuilt']);
        $this->assertSame([0, false], [$h->goal('Q06')['count'], $h->goal('Q06')['met']]);
        $after = $h->app->store->latestReport($h->sessionId());
        $sections = array_column($after['summary']['teacher']['sections'], 'lines', 'heading');
        $this->assertSame([], $sections['Goals met']);
        $this->assertSame(['On “Solve 2x + 5 = 17 for x.”, the recorded result was corrected from correct to incorrect on 2026-10-21: The student said they guessed; the teacher marks it not yet.'], $sections['Corrections']);
        $this->assertSame([3, 2, 1], [$after['summary']['teacher']['skills'][0]['independent'], $after['per_skill'][0]['check_correct'], $after['per_skill'][0]['check_not_yet']], 'two correct, one not yet');
        $this->assertSame([], $after['report']['immediate_criteria']);
        $this->assertSame($after['report']['report_id'], $h->request('GET', 'report')->body['report']['report_id'], 'the stored report is the corrected one');
        $learner = json_encode($after['summary']['learner']);
        $this->assertStringNotContainsString('corrected', $learner, 'the learner view uses the corrected counts only');

        // A suspended verdict (a verdict conflict) corrected: the hold ends and the attempt counts with the corrected verdict.
        $g = $this->withCheck();
        $g->mock->queue('VERDICT_CONFLICT');
        $g->answerCorrectly();
        $a = $g->attempts()[0];
        $this->assertSame([0, 'VERDICT_CONFLICT'], [$g->goal('Q06')['count'], $a['held']]);
        $g->app->engine->applyCorrection($g->sessionId(), $a['attempt_id'], 'verdict', 'CORRECT', 'Reviewed: code\'s verdict stands.');
        $this->assertSame([null, 'CORRECT', 1], [$g->attempts()[0]['held'], $g->attempts()[0]['verdict'], $g->goal('Q06')['count']]);
        $sections = array_column($g->request('GET', 'report')->body['summary']['teacher']['sections'], 'lines', 'heading');
        $this->assertSame([], $sections['Held check answers']);
        $this->assertCount(1, $sections['Corrections']);
        $this->assertStringContainsString('corrected from correct to correct', $sections['Corrections'][0]);
        // Without a correction there is no Corrections section.
        $this->assertArrayNotHasKey('Corrections', array_column($this->withCheck()->request('GET', 'report')->body['summary']['teacher']['sections'], 'lines', 'heading'));
    }

    /** §4.7 test 3: the original record and the correction both stay traceable, and nothing is counted twice. */
    public function testCorrectionsAreTraceableAndNothingCountsTwice(): void
    {
        $h = $this->metQ06();
        $first = $h->attempts()[0];
        $recorded = $h->eventsOf('ATTEMPT_RECORDED');
        $h->app->engine->applyCorrection($h->sessionId(), $first['attempt_id'], 'help', 'with_help', 'First reason.');
        $h->app->engine->applyCorrection($h->sessionId(), $first['attempt_id'], 'help', 'without_help', 'Second thoughts: no help was given.');
        $this->assertSame($recorded, $h->eventsOf('ATTEMPT_RECORDED'), 'the original attempt events are unchanged');
        $corrections = $h->eventsOf('CORRECTION');
        $this->assertSame([['help', 'with_help', 'without_help'], ['help', 'without_help', 'with_help']],
            array_map(fn ($e) => [$e['data']['field'], $e['data']['value'], $e['data']['previous']], $corrections));
        $this->assertSame([$first['attempt_id'], $first['attempt_id']], array_column(array_column($corrections, 'data'), 'attempt_id'));
        $attempts = $h->attempts();
        $this->assertCount(3, $attempts, 'no attempt is added or duplicated');
        $this->assertSame([[], [], 2], [$attempts[0]['help'], $attempts[0]['original']['help'], count($attempts[0]['corrections'])], 'the latest correction applies; both are listed');
        $this->assertSame([3, true], [$h->goal('Q06')['count'], $h->goal('Q06')['met']], 'counted once');
        $this->assertSame(['GOAL_MET', 'GOAL_SUSPENDED', 'GOAL_MET'], array_column(array_values(array_filter($h->events(), fn ($e) => in_array($e['type'], ['GOAL_MET', 'GOAL_SUSPENDED'], true))), 'type'));
        $report = $h->request('GET', 'report')->body;
        $this->assertSame(3, $report['summary']['teacher']['skills'][0]['independent']);
        $this->assertSame(3, array_sum(array_map(fn ($e) => count($e['event_ids']), $report['report']['independent_evidence'])), 'each check counted once');
        $this->assertCount(2, array_column($report['summary']['teacher']['sections'], 'lines', 'heading')['Corrections']);
        // Refused: an unknown attempt, an invalid value, an empty reason.
        foreach ([['evt_nope', 'help', 'with_help', 'x', 'ATTEMPT_NOT_FOUND'], [$first['attempt_id'], 'help', 'maybe', 'x', 'INVALID_CORRECTION'],
            [$first['attempt_id'], 'held', 'none', 'x', 'INVALID_CORRECTION'], [$first['attempt_id'], 'verdict', 'CORRECT', ' ', 'INVALID_CORRECTION']] as [$id, $field, $value, $reason, $code]) {
            try {
                $h->app->engine->applyCorrection($h->sessionId(), $id, $field, $value, $reason);
                $this->fail('expected ' . $code);
            } catch (\AlgebraEngine\Tutor\EngineError $e) {
                $this->assertSame($code, $e->errorCode);
            }
        }
        $this->assertCount(2, $h->eventsOf('CORRECTION'));
    }

    /** §4.7: the private CLI tool appends the correction; --list shows the corrected values. */
    public function testTheCorrectionCliTool(): void
    {
        $h = $this->metQ06();
        $id = $h->attempts()[1]['attempt_id'];
        $config = sys_get_temp_dir() . '/ae_r9_cli_' . bin2hex(random_bytes(4)) . '.php';
        file_put_contents($config, '<?php return ' . var_export(['db_path' => $h->dbPath], true) . ';');
        $run = function (array $args) use ($config): array {
            $cmd = 'ALGEBRA_CONFIG=' . escapeshellarg($config) . ' ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(ALGEBRA_APP_ROOT . '/tools/correct_attempt.php')
                . ' ' . implode(' ', array_map('escapeshellarg', $args)) . ' 2>&1';
            exec($cmd, $out, $code);
            return [$code, implode("\n", $out)];
        };
        [$code, $out] = $run(['--session=' . $h->sessionId(), '--attempt=' . $id, '--field=help', '--value=with_help', '--reason=Help was given at the desk.']);
        $this->assertSame(0, $code, $out);
        $result = json_decode($out, true);
        $this->assertSame(['help', 'without_help', 'with_help', false], [$result['field'], $result['previous'], $result['value'], $result['goals'][0]['met']]);
        $this->assertSame(['CORRECTION'], $h->attempts()[1]['help']);
        [$code, $out] = $run(['--session=' . $h->sessionId(), '--list']);
        $this->assertSame(0, $code, $out);
        $list = json_decode($out, true);
        $this->assertSame(['with_help', 'Help was given at the desk.'], [$list[1]['help'], $list[1]['corrections'][0]['reason']]);
        [$code] = $run(['--session=' . $h->sessionId(), '--attempt=' . $id, '--field=help', '--value=sometimes', '--reason=x']);
        $this->assertSame(1, $code);
        [$code] = $run(['--session=' . $h->sessionId()]);
        $this->assertSame(2, $code, 'usage');
        unlink($config);
    }

    // ------------------------------------------------------- §4.6 detectors

    /** §4.6: tokenizing fixes, the skill-code detector, and the two counters' scope. */
    public function testDetectorsAndCounters(): void
    {
        $choice = fn (string $text, string $letter) => DisclosureGuard::violations($text, [['answer' => ['kind' => 'classification', 'expected' => [$letter]],
            'options' => [['letter' => 'A', 'text' => '3x + 12'], ['letter' => 'B', 'text' => '3x + 4'], ['letter' => 'C', 'text' => 'x + 12']]]]) !== [];
        // An option letter counts only when it stands alone.
        $this->assertFalse($choice('The distributive property says a(b + c) = ab + ac, so the factor multiplies every term.', 'C'));
        $this->assertFalse($choice('Solve a(x + b) = c by dividing first.', 'B'));
        $this->assertTrue($choice('The answer is C.', 'C'));
        $this->assertTrue($choice('C) is the one.', 'C'));
        $this->assertTrue($choice('Option B fits.', 'B'));
        // Number words count only as standalone words.
        $scalar = fn (string $text, string $answer) => DisclosureGuard::violations($text, [['answer' => ['kind' => 'exact_rational', 'expected' => $answer]]]) !== [];
        $this->assertFalse($scalar('To solve a two-step equation, undo the addition first.', '2'));
        $this->assertTrue($scalar('It comes to two.', '2'));
        $this->assertSame([], Numbers::words('a two-step equation'));
        $this->assertSame([25], Numbers::words('twenty-five'));
        // Digits inside codes never count.
        $this->assertSame([], Numbers::digits('Your Q07 goal'));
        $this->assertSame(['7'], Numbers::digits('x = 7'));
        $card = (new ProblemFactory(new ItemFactory()))->fromRecipe('ONE_STEP_MULTIPLY', ['a' => 3, 'b' => 21], 'CHECK'); // x = 7
        $this->assertSame([], TextGuard::beforeCheckCard('Your Q07 goal is met, so let\'s keep your two-step equation skill sharp.', $card)['shared']);
        $this->assertFalse(TextGuard::beforeCheckCard('Your Q07 goal is met.', $card)['answer_in_text']);
        // Skill codes in learner text are detected (logged).
        $this->assertSame(['Q07', 'E06'], TextGuard::skillCodes('Your Q07 goal is met; E06 next. Q7 and AB12 and x07 are not codes.'));
        $this->assertContains('SKILL_CODE', array_column(TextGuard::findings('Your Q07 goal is met.', []), 'guard'));
        // RESOLVE_AFTER_CORRECT leaves out substitution checks.
        $p = ['kind' => 'linear_equation', 'prompt' => 'Solve 4x + 6 = 46 for x.', 'answer' => ['kind' => 'exact_rational', 'expected' => '10'], 'variables' => ['x'], 'numbers' => ['4', '6', '46']];
        $this->assertSame([], Counters::resolveAfterCorrect('Correct — you answered 10, and 4·10 + 6 = 46 checks out.', $p, ['10']));
        $this->assertSame([], Counters::resolveAfterCorrect('Correct: 4·10 + 6 = 40 + 6 = 46.', $p, ['10']));
        $this->assertSame(['4x = 40', 'value 40'], Counters::resolveAfterCorrect('Correct. 4x = 40, so x = 10.', $p, ['10']), 'an intermediate step still counts');
        $n = ['kind' => 'linear_equation', 'prompt' => 'Solve −3x + 9 = 33 for x.', 'answer' => ['kind' => 'exact_rational', 'expected' => '-8'], 'variables' => ['x'], 'numbers' => ['3', '9', '33']];
        $this->assertSame([], Counters::resolveAfterCorrect('Correct — you answered -8, and -3·(-8) + 9 = 33 checks out.', $n, ['-8']));
        // VALUE_QUESTION_IN_TEXT counts requests for an expression and leaves out questions that ask for an operation.
        $this->assertSame(['what is 6x + 60 written as the product of 6 and a grouped sum?'],
            Counters::valueQuestions('Now put that 60 back into the expansion: what is 6x + 60 written as the product of 6 and a grouped sum?'));
        $this->assertSame(['How would you write 6x + 60 as a product?'], Counters::valueQuestions('How would you write 6x + 60 as a product?'));
        $this->assertSame([], Counters::valueQuestions('What is the opposite of adding 2?'));
        $this->assertSame([], Counters::valueQuestions('Which operation undoes the + 6 in 3x + 6 = 15?'));
        $this->assertSame(['What is 3x + 6 - 6?'], Counters::valueQuestions('What is 3x + 6 - 6?'));
    }
}
