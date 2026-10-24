<?php
declare(strict_types=1);

namespace AlgebraEngine\Tests\Integration;

use AlgebraEngine\Items\ItemFactory;
use AlgebraEngine\Report\TutorReport;
use AlgebraEngine\Tests\Support\Harness;
use AlgebraEngine\Tutor\AnswerCheck;
use AlgebraEngine\Tutor\Goals;
use AlgebraEngine\Tutor\Gradebook;
use AlgebraEngine\Tutor\Numbers;
use AlgebraEngine\Tutor\ProblemFactory;
use AlgebraEngine\Tutor\TextGuard;
use AlgebraEngine\Tutor\TutorEngine;
use AlgebraEngine\Tutor\TutorPrompt;
use PHPUnit\Framework\TestCase;

/**
 * Round 10 §5: the targeted offline tests, one group per case (1 to 13).
 * Mock tutor throughout: none of this is evidence of live-provider behaviour
 * or of learning.
 */
final class Round10Test extends TestCase
{
    // ------------------------------------------------------------ helpers

    private static function item(string $purpose, string $skill, string $kind, string $expr, string $stated, array $spec = []): array
    {
        return ['purpose' => $purpose, 'skill_id' => $skill, 'source' => 'spec', 'recipe_ref' => null,
            'spec' => ['kind' => $kind, 'expression' => $expr] + $spec, 'prompt' => '', 'stated_answer' => $stated];
    }

    private static function step(string $expr, string $operation, int $operand, string $stated, string $skill = 'Q06'): array
    {
        return self::item('practice', $skill, 'equation_step', $expr, $stated, ['operation' => $operation, 'operand' => $operand]);
    }

    /** Queue a reply (with or without an item) under the given move, then send a message with no number in it. */
    private static function reply(Harness $h, ?array $item, string $move = 'POSE_PROBLEM', string $text = 'Here is the next one.', string $say = 'ok, what next?', array $extra = []): void
    {
        $h->mock->queue('ENVELOPE', ['set' => ['student_message' => $text, 'item' => $item,
            'proposed_transition' => ['to_mode' => $move, 'target_skill_id' => $item['skill_id'] ?? null, 'reason' => 'test', 'evidence_event_ids' => []]] + $extra]);
        self::assertSame(200, $h->send($say)->status);
    }

    private static function pose(Harness $h, array $item): void
    {
        self::reply($h, $item);
        self::assertSame($item['purpose'], $h->view['open_problem']['purpose'] ?? null, 'the posed problem is open: ' . json_encode(end($h->view['conversation'])));
    }

    /** Help on the open check: HINT, with or without a practice item. */
    private static function hint(Harness $h, ?array $item = null, string $say = 'can I have a hint?'): void
    {
        self::reply($h, $item, 'HINT', 'Think about which operation to undo first.', $say);
    }

    /** Answer the open problem; the reply that follows poses nothing (so the mock does not bring the original back on its own). */
    private static function answer(Harness $h, ?string $entry = null): void
    {
        $h->mock->queue('ENVELOPE', ['set' => ['student_message' => 'Thanks.', 'item' => null, 'proposed_transition' => null]]);
        self::assertSame(200, $h->send($entry ?? $h->answer())->status);
    }

    /** A message; the reply poses nothing. */
    private static function say(Harness $h, string $text = 'ok'): void
    {
        $h->mock->queue('ENVELOPE', ['set' => ['student_message' => 'Thanks.', 'item' => null, 'proposed_transition' => null]]);
        self::assertSame(200, $h->send($text)->status);
    }

    private static function routeKinds(Harness $h): array
    {
        return array_map(fn ($e) => [$e['data']['kind'], $e['data']['skill_id']], $h->eventsOf('ROUTE'));
    }

    private static function eng(Harness $h): array
    {
        return $h->row()['engine'];
    }

    private static function resultText(Harness $h): string
    {
        $lines = array_values(array_filter($h->lines(), fn ($l) => $l['kind'] === 'result'));
        return (string) end($lines)['text'];
    }

    private function rejectedWith(Harness $h, string $needle): void
    {
        $errors = [];
        foreach ($h->eventsOf('MODEL_OUTPUT_REJECTED') as $e) {
            $errors = array_merge($errors, $e['data']['errors']);
        }
        $this->assertNotSame([], array_filter($errors, fn ($e) => str_contains($e, $needle)), 'expected a rejection containing "' . $needle . '"; got ' . json_encode($errors));
    }

    // ------------------------------------------------- 1. the reset loophole

    /** §5.1: two correct checks, help, the original set aside, a later correct check: the earlier two don't carry over. */
    public function testTheResetLoophole(): void
    {
        $h = new Harness();
        $h->start('Q06');
        foreach (['2x + 3 = 11' => '4', '3x + 4 = 19' => '5'] as $expr => $x) {
            self::pose($h, self::item('check', 'Q06', 'linear_equation', $expr, $x));
            self::answer($h);
        }
        $this->assertSame([2, false], [$h->goal('Q06')['count'], $h->goal('Q06')['met']]);
        self::pose($h, self::item('check', 'Q06', 'linear_equation', '4x + 1 = 29', '7'));
        $helped = $h->open();
        self::hint($h);
        $this->assertSame(0, $h->goal('Q06')['count'], 'the run restarts when the help is recorded');
        self::pose($h, self::item('check', 'Q06', 'linear_equation', '6x + 5 = 53', '8'));
        $this->assertSame([['RETURN_FRESH', 'Q06'], ['ORIGINAL_SET_ASIDE', 'Q06']], self::routeKinds($h));
        $this->assertSame($helped['problem_id'], self::eng($h)['set_aside_original']);
        self::answer($h);
        $this->assertSame([1, false], [$h->goal('Q06')['count'], $h->goal('Q06')['met']], 'the two earlier checks do not carry over');
        $this->assertSame([], $h->eventsOf('GOAL_MET'));
    }

    // ----------------------------------------------------- 2. no double reset

    /** §5.2: a calculation test of Goals::compute() on a constructed event record. */
    public function testNoDoubleReset(): void
    {
        $seq = 0;
        $events = [];
        $add = function (string $type, array $data) use (&$events, &$seq): void {
            $events[] = ['event_id' => 'evt' . (++$seq), 'type' => $type, 'revision' => $seq, 'data' => $data, 'created_at' => '2026-10-24T10:00:00Z'];
        };
        $present = fn (string $id, string $prompt) => $add('PROBLEM_PRESENTED', ['problem_id' => $id, 'version' => 1, 'purpose' => 'CHECK', 'skill_id' => 'Q06',
            'variant_id' => 'constructed', 'template_id' => 'TWO_STEP', 'prompt' => $prompt, 'retry_of' => null]);
        $answer = fn (string $id, string $entry) => $add('ATTEMPT_RECORDED', ['problem_id' => $id, 'version' => 1, 'entry' => $entry, 'read_as' => $entry,
            'reading_note' => null, 'verdict' => 'CORRECT', 'signatures' => [], 'slip' => null]);
        $present('A', 'Solve 2x + 3 = 11 for x.');
        $add('HELP_GIVEN', ['problem_id' => 'A', 'version' => 1, 'choice' => 'HINT', 'source' => 'TUTOR_MOVE']);
        $present('B', 'Solve 3x + 4 = 19 for x.');
        $answer('B', '5');
        $present('C', 'Solve 4x + 1 = 29 for x.');
        $answer('C', '7');
        $answer('A', '4'); // the helped check, answered later with help

        $goals = Goals::compute(Gradebook::attempts($events), Gradebook::checkHelp($events));
        $this->assertSame([2, false], [$goals['Q06']['count'], $goals['Q06']['met']], 'the two fresh checks still count; A adds no credit and no second reset');
        $this->assertSame(['HINT'], Gradebook::attempts($events)[2]['help'], 'A is recorded with help');
        // Without the help events (the round 9 calculation) A's answer would reset the run a second time.
        $this->assertSame(0, Goals::compute(Gradebook::attempts($events))['Q06']['count']);

        $present('D', 'Solve 5x + 2 = 37 for x.');
        $answer('D', '7');
        $goals = Goals::compute(Gradebook::attempts($events), Gradebook::checkHelp($events));
        $this->assertSame([3, true], [$goals['Q06']['count'], $goals['Q06']['met']]);
        $this->assertSame($goals, Goals::fromEvents($events));
    }

    // -------------------------------------------- 3. a step back to a prerequisite

    /** §5.3: a step back keeps TARGET and the waiting ORIGINAL_PROBLEM, and records a DETOUR. */
    public function testAStepBackKeepsTargetAndOriginalAndRecordsADetour(): void
    {
        $h = new Harness();
        $h->start('Q07');
        self::pose($h, self::item('check', 'Q07', 'linear_equation', '3(x + 2) = 21', '5'));
        $check = $h->open();
        self::hint($h, self::item('practice', 'E06', 'expand', '3(x + 2)', '3x + 6'));
        $this->assertSame('E06', $h->open()['skill_id']);
        $this->assertSame([['DETOUR', 'E06']], self::routeKinds($h));
        self::answer($h);
        $ctx = TutorLoopTest::context($h);
        $this->assertSame(['Q07', 'Solve equations containing parentheses', false, null], array_values($ctx['TARGET']));
        $this->assertSame(['skill_id', 'skill_name', 'goal_met', 'set_aside_original'], array_keys($ctx['TARGET']));
        $this->assertSame($check['problem_id'], $ctx['ORIGINAL_PROBLEM']['problem_id']);
        $this->assertSame(['DETOUR', 'E06'], [$ctx['ROUTE'][0]['kind'], $ctx['ROUTE'][0]['skill_id']]);
        $this->assertSame(['kind', 'skill_id', 'problem_id', 'at'], array_keys($ctx['ROUTE'][0]));
        $this->assertSame('Q07', $ctx['TOPIC']['skill_id'], 'the target is kept');
        // Bringing the original back is recorded too.
        self::reply($h, ['purpose' => 'practice', 'skill_id' => 'Q07', 'source' => 'original', 'recipe_ref' => null, 'spec' => null, 'prompt' => '', 'stated_answer' => null], 'POSE_PROBLEM', 'Back to the first one.');
        $this->assertSame($check['problem_id'], $h->open()['problem_id']);
        $this->assertSame([['DETOUR', 'E06'], ['RETURN_ORIGINAL', 'Q07']], self::routeKinds($h));
    }

    // ------------------------------------- 4. a fresh check while help is recorded

    /** §5.4: a fresh check on the target is available while an old helped problem is still recorded. */
    public function testAFreshCheckIsAvailableWhileAHelpedProblemIsRecorded(): void
    {
        $h = new Harness();
        $h->start('Q06');
        self::pose($h, self::item('check', 'Q06', 'linear_equation', '2x + 3 = 11', '4'));
        self::say($h);
        $this->assertFalse(TutorLoopTest::context($h)['ALLOWED']['may_pose_check'], 'an unanswered check with no help blocks a check');
        self::hint($h);
        $this->assertNotSame([], $h->eventsOf('HELP_GIVEN'));
        self::say($h);
        $this->assertTrue(TutorLoopTest::context($h)['ALLOWED']['may_pose_check'], 'helped and still open: no block');
        self::reply($h, self::step('2x + 3 = 11', 'subtract', 3, '2x = 8'), 'POSE_PROBLEM', 'Try this step.');
        $this->assertNotNull(self::eng($h)['original'], 'the helped check waits as the original');
        self::say($h);
        $ctx = TutorLoopTest::context($h);
        $this->assertSame([true, true], [$ctx['ALLOWED']['may_pose_problem'], $ctx['ALLOWED']['may_pose_check']], 'a waiting original blocks nothing');
        $rejected = count($h->eventsOf('MODEL_OUTPUT_REJECTED'));
        self::pose($h, self::item('check', 'Q06', 'linear_equation', '5x + 4 = 39', '7'));
        $this->assertSame($rejected, count($h->eventsOf('MODEL_OUTPUT_REJECTED')), 'the fresh check is accepted');
    }

    // --------------------------------------------- 5. setting the original aside

    private function waitingOriginal(): array
    {
        $h = new Harness();
        $h->start('Q06');
        self::pose($h, self::item('check', 'Q06', 'linear_equation', '2x + 3 = 11', '4'));
        $original = $h->open();
        self::hint($h, self::step('2x + 3 = 11', 'subtract', 3, '2x = 8'));
        $this->assertSame($original['problem_id'], self::eng($h)['original']['problem_id']);
        self::answer($h);
        return [$h, $original];
    }

    /** §5.5: an accepted fresh check on the target sets the original aside; it is not completed and the run is not reset again. */
    public function testAnAcceptedFreshCheckSetsTheOriginalAside(): void
    {
        [$h, $original] = $this->waitingOriginal();
        $helps = count($h->eventsOf('HELP_GIVEN'));
        self::pose($h, self::item('check', 'Q06', 'linear_equation', '5x + 4 = 39', '7'));
        $fresh = $h->open();
        $route = array_map(fn ($e) => [$e['data']['kind'], $e['data']['problem_id']], $h->eventsOf('ROUTE'));
        $this->assertSame([['RETURN_FRESH', $fresh['problem_id']], ['ORIGINAL_SET_ASIDE', $original['problem_id']]], $route);
        $closed = array_values(array_filter($h->eventsOf('PROBLEM_CLOSED'), fn ($e) => $e['data']['problem_id'] === $original['problem_id']));
        $this->assertSame(['SET_ASIDE', 'a fresh check question on the target skill was accepted'], [$closed[0]['data']['reason'], $closed[0]['data']['original_set_aside']]);
        $this->assertCount(1, $closed);
        $this->assertNull(self::eng($h)['original']);
        $this->assertSame($helps, count($h->eventsOf('HELP_GIVEN')), 'help stays recorded once');
        self::answer($h);
        $ctx = TutorLoopTest::context($h);
        $this->assertSame($original['problem_id'], $ctx['TARGET']['set_aside_original']);
        $this->assertNull($ctx['ORIGINAL_PROBLEM']);
        foreach (['3x + 1 = 22' => '7', '4x + 6 = 38' => '8'] as $expr => $x) {
            self::pose($h, self::item('check', 'Q06', 'linear_equation', $expr, $x));
            self::answer($h);
        }
        $this->assertSame([3, true], [$h->goal('Q06')['count'], $h->goal('Q06')['met']], 'setting the original aside did not reset the run again');
        $this->assertSame([], array_filter($h->attempts(), fn ($a) => $a['problem_id'] === $original['problem_id']), 'the original was never answered');
        $this->assertNotContains('ORIGINAL_COMPLETED_WITH_HELP', array_column(array_column($h->eventsOf('ROUTE'), 'data'), 'kind'));
    }

    /** §5.5: a check proposal that code rejects changes nothing. */
    public function testARejectedCheckProposalChangesNothing(): void
    {
        [$h, $original] = $this->waitingOriginal();
        $route = $h->eventsOf('ROUTE');
        $h->mock->queue('ENVELOPE', ['set' => ['student_message' => 'Here is a check question.', 'item' => self::item('check', 'Q06', 'linear_equation', '5x + 4 = 39', '8'),
            'proposed_transition' => ['to_mode' => 'POSE_PROBLEM', 'target_skill_id' => 'Q06', 'reason' => 'test', 'evidence_event_ids' => []]]]);
        $h->mock->queue('ENVELOPE', ['repair' => true, 'set' => ['student_message' => 'Let us talk it through first.', 'item' => null, 'proposed_transition' => null]]);
        $h->send('ok, what next?');
        $this->rejectedWith($h, 'stated answer');
        $this->assertSame($route, $h->eventsOf('ROUTE'), 'no route entry');
        $this->assertSame($original['problem_id'], self::eng($h)['original']['problem_id'], 'the original still waits');
        $this->assertNull(self::eng($h)['set_aside_original']);
        $this->assertSame([], array_filter($h->eventsOf('PROBLEM_CLOSED'), fn ($e) => $e['data']['problem_id'] === $original['problem_id']));
    }

    /** §5.5: an easier practice problem on the same skill (Expand 2(x + 3) after Expand 7(5x + 8)) does not set the original aside. */
    public function testEasierPracticeOnTheSameSkillDoesNotSetTheOriginalAside(): void
    {
        $h = new Harness();
        $h->start('E06');
        self::pose($h, self::item('check', 'E06', 'expand', '7(5x + 8)', '35x + 56'));
        $original = $h->open();
        self::hint($h, self::item('practice', 'E06', 'expand', '2(x + 3)', '2x + 6'));
        $this->assertSame('Expand 2(x + 3). Write your answer without parentheses.', $h->view['open_problem']['prompt']);
        self::answer($h);
        self::pose($h, self::item('practice', 'E06', 'expand', '4(x + 1)', '4x + 4'));
        self::answer($h);
        $this->assertSame($original['problem_id'], self::eng($h)['original']['problem_id'], 'practice never sets the original aside');
        $this->assertSame([], self::routeKinds($h));
        $this->assertNull(TutorLoopTest::context($h)['TARGET']['set_aside_original']);
    }

    // ------------------------------------- 6. completion through connected steps

    /** §5.6: a correct chain from the original equation to its solution records ORIGINAL_COMPLETED_WITH_HELP, with no goal credit. */
    public function testAConnectedChainCompletesTheOriginalWithHelp(): void
    {
        $h = new Harness();
        $h->start('Q06');
        self::pose($h, self::item('check', 'Q06', 'linear_equation', '3x + 6 = 15', '3'));
        $original = $h->open();
        self::hint($h, self::step('3x + 6 = 15', 'subtract', 6, '3x = 9'));
        self::answer($h, '3x = 9');
        $this->assertSame([], $h->eventsOf('ROUTE'), 'one step does not finish it');
        self::pose($h, self::step('3x = 9', 'divide', 3, 'x = 3'));
        self::answer($h, 'x = 3');
        $this->assertSame([['ORIGINAL_COMPLETED_WITH_HELP', $original['problem_id']]], array_map(fn ($e) => [$e['data']['kind'], $e['data']['problem_id']], $h->eventsOf('ROUTE')));
        $closed = array_values(array_filter($h->eventsOf('PROBLEM_CLOSED'), fn ($e) => $e['data']['problem_id'] === $original['problem_id']));
        $this->assertSame('COMPLETED_WITH_HELP', $closed[0]['data']['reason']);
        $this->assertNull(self::eng($h)['original']);
        $this->assertSame([0, false], [$h->goal('Q06')['count'], $h->goal('Q06')['met']], 'no goal credit');
        $this->assertSame([], array_filter($h->attempts(), fn ($a) => $a['purpose'] === 'CHECK'), 'no check answer was recorded');
        $this->assertNull(TutorLoopTest::context($h)['ORIGINAL_PROBLEM']);
    }

    /** §5.6: a final answer that only happens to match, or other arithmetic, never completes the original. */
    public function testACoincidentalAnswerOrOtherArithmeticDoesNotComplete(): void
    {
        $h = new Harness();
        $h->start('Q06');
        self::pose($h, self::item('check', 'Q06', 'linear_equation', '3x + 6 = 15', '3'));
        $original = $h->open();
        // An unrelated chain that ends at x = 3.
        self::hint($h, self::step('2x + 1 = 7', 'subtract', 1, '2x = 6'));
        self::answer($h, '2x = 6');
        self::pose($h, self::step('2x = 6', 'divide', 2, 'x = 3'));
        self::answer($h, 'x = 3');
        $this->assertSame('CORRECT', $h->attempts()[1]['verdict']);
        // Unrelated arithmetic whose result is the original's solution.
        self::pose($h, self::item('practice', 'Q06', 'numeric_step', '12 - 9', '3'));
        self::answer($h, '3');
        $this->assertSame('CORRECT', $h->attempts()[2]['verdict']);
        $this->assertSame([], $h->eventsOf('ROUTE'));
        $this->assertSame($original['problem_id'], self::eng($h)['original']['problem_id'], 'the original still waits');
        // A step from the original's equation alone (not reaching the solution) does not complete it either.
        self::pose($h, self::step('3x + 6 = 15', 'subtract', 6, '3x = 9'));
        self::answer($h, '3x = 9');
        $this->assertSame($original['problem_id'], self::eng($h)['original']['problem_id']);
    }

    // ------------------------------------ 7. several prerequisite problems wrong

    /** §5.7: several prerequisite problems answered wrongly before success on an easier one: the route records each step back. */
    public function testSeveralPrerequisiteProblemsWrongThenSuccess(): void
    {
        $h = new Harness();
        $h->start('Q07');
        self::pose($h, self::item('practice', 'E06', 'expand', '4(x + 5)', '4x + 20'));
        self::answer($h, '4x + 5');
        self::pose($h, self::item('practice', 'E06', 'expand', '3(x + 6)', '3x + 18'));
        self::answer($h, '3x + 6');
        self::pose($h, self::item('practice', 'N18', 'numeric_step', '3 * 6', '18'));
        self::answer($h, '9');
        self::pose($h, self::item('practice', 'N18', 'numeric_step', '2 * 4', '8'));
        self::answer($h);
        $this->assertSame(['INCORRECT', 'INCORRECT', 'INCORRECT', 'CORRECT'], array_column($h->attempts(), 'verdict'));
        $this->assertSame([['DETOUR', 'E06'], ['DETOUR', 'N18']], self::routeKinds($h), 'each step back to a prerequisite is recorded');
        $route = TutorReport::routeSummary($h->events(), $h->attempts(), Gradebook::problems($h->events()));
        $this->assertSame([['E06', 2, 0, 2], ['N18', 2, 1, 1]], array_map(fn ($d) => [$d['skill_id'], $d['problems'], $d['correct'], $d['not_yet']], $route['detours']));
        self::pose($h, self::item('practice', 'Q07', 'linear_equation', '2(x + 1) = 10', '4'));
        $this->assertSame([['DETOUR', 'E06'], ['DETOUR', 'N18'], ['RETURN_TO_SKILL', 'Q07']], self::routeKinds($h));
        self::answer($h);
        $h->event('END_SESSION', [], null, null, false);
        $sections = array_column($h->request('GET', 'report')->body['summary']['teacher']['sections'], 'lines', 'heading');
        $this->assertSame([
            'Detour to Apply the distributive property with positive factors (E06): 2 problems, 0 correct and 2 not yet. Work returned to Solve equations containing parentheses (Q07) through practice. On Solve equations containing parentheses (Q07) afterwards: 1 correct and 0 not yet.',
            'Detour to Multiply and divide whole numbers (N18): 2 problems, 1 correct and 1 not yet. Work returned to Solve equations containing parentheses (Q07) through practice. On Solve equations containing parentheses (Q07) afterwards: 1 correct and 0 not yet.',
            'Fresh evidence on Solve equations containing parentheses (Q07) without help after the return: 0 check answers counted, 0 correct.',
            'These are evidence, not a score.',
        ], $sections['Route and return']);
    }

    // ------------------------------------------------------ 8. goal_newly_met

    /** §5.8: goal_newly_met is in the request before the tutor's reply on the turn a goal is met, and absent on the next turn. */
    public function testGoalNewlyMetIsInTheRequestOnlyOnTheTurnTheGoalIsMet(): void
    {
        $h = new Harness();
        $h->start('Q06');
        $seen = [];
        foreach (['2x + 3 = 11' => '4', '3x + 4 = 19' => '5', '4x + 1 = 29' => '7'] as $expr => $x) {
            self::pose($h, self::item('check', 'Q06', 'linear_equation', $expr, $x));
            self::answer($h);
            $seen[] = TutorLoopTest::context($h)['CODE_RESULT']['goal_newly_met'];
        }
        $this->assertSame([null, null, ['skill_id' => 'Q06', 'skill_name' => 'Solve two-step equations']], $seen);
        $this->assertCount(1, $h->eventsOf('GOAL_MET'), 'GOAL_MET is still written at commit');
        self::say($h, 'thanks');
        $this->assertNull(TutorLoopTest::context($h)['CODE_RESULT'], 'absent on the next turn');
        self::pose($h, self::item('check', 'Q06', 'linear_equation', '5x + 2 = 37', '7'));
        self::answer($h);
        $this->assertNull(TutorLoopTest::context($h)['CODE_RESULT']['goal_newly_met'], 'already met: not newly met');
    }

    // ------------------------------------------- 9. a correction of the latest attempt

    /** §5.9: the next request after correcting the latest attempt shows the corrected verdict in ANSWERED_PROBLEM, GRADEBOOK and GOALS. */
    public function testTheNextRequestShowsACorrectionOfTheLatestAttempt(): void
    {
        $h = new Harness();
        $h->start('Q06');
        self::pose($h, self::item('check', 'Q06', 'linear_equation', '2x + 3 = 11', '4'));
        self::answer($h);
        $attempt = $h->attempts()[0];
        $ctx = TutorLoopTest::context($h);
        $this->assertSame(['CORRECT', 1], [$ctx['ANSWERED_PROBLEM']['result'], $ctx['GRADEBOOK']['session_totals_by_skill']['Q06']['check_correct_no_recorded_help']]);
        $h->app->engine->applyCorrection($h->sessionId(), $attempt['attempt_id'], 'verdict', 'INCORRECT', 'The teacher marks it not yet.');
        $this->assertSame('INCORRECT', self::eng($h)['answered']['verdict'], 'the stored record of the last answered problem is updated');
        $h->view['revision'] = $h->row()['revision'];
        self::say($h);
        $ctx = TutorLoopTest::context($h);
        $this->assertSame([$attempt['attempt_id'], 'INCORRECT'], [$ctx['ANSWERED_PROBLEM']['attempt_id'], $ctx['ANSWERED_PROBLEM']['result']]);
        $totals = $ctx['GRADEBOOK']['session_totals_by_skill']['Q06'];
        $this->assertSame([0, 0, 1], [$totals['check_correct_no_recorded_help'], $totals['check_correct_with_help'], $totals['check_not_yet']]);
        $this->assertSame('INCORRECT', end($ctx['GRADEBOOK']['recent_attempts'])['result']);
        $q06 = array_values(array_filter($ctx['GOALS'], fn ($g) => $g['skill_id'] === 'Q06'))[0];
        $this->assertSame([0, false], [$q06['correct_in_a_row'], $q06['met']]);
        // A help correction shows the same way.
        $h->app->engine->applyCorrection($h->sessionId(), $attempt['attempt_id'], 'verdict', 'CORRECT', 'Reviewed again.');
        $h->app->engine->applyCorrection($h->sessionId(), $attempt['attempt_id'], 'help', 'with_help', 'The teacher sat with the student.');
        $h->view['revision'] = $h->row()['revision'];
        self::say($h);
        $ctx = TutorLoopTest::context($h);
        $totals = $ctx['GRADEBOOK']['session_totals_by_skill']['Q06'];
        $this->assertSame([0, 1], [$totals['check_correct_no_recorded_help'], $totals['check_correct_with_help']]);
        $this->assertSame(['CORRECTION'], end($ctx['GRADEBOOK']['recent_attempts'])['help']);
        $this->assertArrayNotHasKey('by_skill', $ctx['GRADEBOOK']);
        $this->assertArrayNotHasKey('check_correct', $totals);
    }

    /** §4.5: slip state derived from the corrected attempt is cleared. */
    public function testACorrectionClearsSlipStateOfThatAttempt(): void
    {
        $h = new Harness();
        $h->start('E06');
        self::pose($h, self::item('check', 'E06', 'expand', '7(x + 5)', '7x + 35'));
        self::answer($h, '7x + 30');
        $this->assertNotNull(self::eng($h)['slip'], 'an eligible slip');
        $attempt = $h->attempts()[0];
        $h->app->engine->applyCorrection($h->sessionId(), $attempt['attempt_id'], 'verdict', 'CORRECT', 'The teacher accepts it.');
        $this->assertNull(self::eng($h)['slip']);
        $h->view['revision'] = $h->row()['revision'];
        self::say($h);
        $this->assertFalse(TutorLoopTest::context($h)['SLIP']['eligible_for_pause']);
    }

    // ------------------------------------------------------------- 10. repairs

    /** §5.10: the repair instruction carries the new sentence, and a repaired reply whose item changed may change its wording. */
    public function testRepairsCarryTheNewSentenceAndMayRewordAChangedItem(): void
    {
        $h = new Harness();
        $h->start('Q06');
        $h->mock->queue('ENVELOPE', ['set' => ['student_message' => 'Try this: 2x + 3 = 11.', 'item' => self::item('practice', 'Q06', 'linear_equation', '2x + 3 = 11', '5'),
            'proposed_transition' => ['to_mode' => 'POSE_PROBLEM', 'target_skill_id' => 'Q06', 'reason' => 'test', 'evidence_event_ids' => []]]]);
        $h->mock->queue('ENVELOPE', ['repair' => true, 'set' => ['student_message' => 'Here is a different one to try.', 'item' => self::item('practice', 'Q06', 'linear_equation', '4x + 2 = 18', '4'),
            'proposed_transition' => ['to_mode' => 'POSE_PROBLEM', 'target_skill_id' => 'Q06', 'reason' => 'test', 'evidence_event_ids' => []]]]);
        $before = $h->lines();
        $h->send('ok, what next?');
        $this->rejectedWith($h, 'stated answer');
        $repair = null;
        foreach ($h->mock->log as $call) {
            $last = end($call['messages'])['content'];
            if (str_contains($last, 'REPAIR_REQUEST')) {
                $repair = $last;
            }
        }
        $this->assertNotNull($repair);
        $this->assertStringContainsString('Fix exactly these errors, update any wording they affect, and change nothing else.', $repair);
        $this->assertStringNotContainsString('Do not change', $repair);
        $added = $h->lastLines($before);
        $this->assertSame([['student', 'ok, what next?'], ['tutor', 'Here is a different one to try.'], ['tutor', 'Solve 4x + 2 = 18 for x.']],
            array_map(fn ($l) => [$l['role'], $l['text']], $added), 'the repaired wording is shown with the changed item');
        $this->assertStringContainsString('Fix exactly these errors, update any wording they affect, and change nothing else.', json_encode((new TutorPrompt($h->app->schemas))->repair([], null, ['x']), JSON_UNESCAPED_UNICODE));
    }

    // ------------------------------------------------------------ 11. message_type

    /** §5.11: message_type is set by code; a reply without it is accepted, and one that still sends it is accepted with code's value. */
    public function testMessageTypeIsSetByCode(): void
    {
        $this->assertStringNotContainsString('message_type', TutorPrompt::OUTPUT_CONTRACT);
        $schema = json_decode((string) file_get_contents(ALGEBRA_APP_ROOT . '/private/resources/contracts/tutor-envelope.schema.json'), true);
        $this->assertArrayNotHasKey('message_type', $schema['properties']);
        $this->assertNotContains('message_type', $schema['required']);

        $h = new Harness();
        $h->start('Q06');
        self::pose($h, self::item('practice', 'Q06', 'linear_equation', '2x + 3 = 11', '4'));
        self::say($h, 'what does solve mean?');
        // The mock sends no message_type at all.
        foreach ($h->mock->log as $call) {
            $this->assertStringNotContainsString('"message_type"', (string) end($call['messages'])['content']);
        }
        $accepted = $h->eventsOf('MODEL_OUTPUT_ACCEPTED');
        $this->assertSame([], $h->eventsOf('MODEL_OUTPUT_REJECTED'));
        $this->assertSame(['QUESTION', 'FEEDBACK'], array_slice(array_column(array_column($accepted, 'data'), 'message_type'), -2), 'code sets it: a posed problem, then a reply that poses none');
        // A reply that still carries a message_type that disagrees is accepted; code's value is used.
        $h->mock->queue('ENVELOPE', ['set' => ['message_type' => 'CONTROL', 'student_message' => 'Good question.', 'item' => null, 'proposed_transition' => null]]);
        $h->send('why?');
        $this->assertSame([], $h->eventsOf('MODEL_OUTPUT_REJECTED'));
        $last = $h->eventsOf('MODEL_OUTPUT_ACCEPTED');
        $this->assertSame('FEEDBACK', end($last)['data']['message_type']);
    }

    // --------------------------------------------------------- 12. result lines

    /** §5.12: the three result-line forms of §4.7. */
    public function testTheThreeResultLineForms(): void
    {
        $h = new Harness();
        $h->start('Q06');
        self::pose($h, self::item('practice', 'Q06', 'linear_equation', '2x + 3 = 11', '4'));
        self::answer($h, '4');
        $this->assertSame("Correct.\nOn 2x + 3 = 11 you answered 4.", self::resultText($h), 'an ordinary answer (unchanged)');

        self::pose($h, self::item('practice', 'Q06', 'linear_equation', '3x + 4 = 19', '5'));
        $h->mock->queue('ENVELOPE', ['set' => ['reading' => ['is_answer' => true, 'text' => '6', 'other_readings' => [], 'clarifying_question' => null]]]);
        self::answer($h, 'My answer is 6 I think');
        $this->assertSame("Not yet.\nOn 3x + 4 = 19, read from your message: 6.", self::resultText($h), 'read from a message');
        $this->assertStringNotContainsString('My answer is', self::resultText($h), 'the message is not repeated');

        self::pose($h, self::step('3x + 6 = 15', 'subtract', 6, '3x = 9'));
        $this->assertSame('Subtract 6 from both sides of 3x + 6 = 15. Write the equation you get.', $h->view['open_problem']['prompt']);
        self::answer($h, '3x = 9');
        $this->assertSame("Correct.\nOn the step “Subtract 6 from both sides of 3x + 6 = 15”, you wrote 3x = 9.", self::resultText($h), 'an equation_step answer');

        self::pose($h, self::step('4x + 2 = 18', 'subtract', 2, '4x = 16'));
        $h->mock->queue('ENVELOPE', ['set' => ['reading' => ['is_answer' => true, 'text' => '4x = 16', 'other_readings' => [], 'clarifying_question' => null]]]);
        self::answer($h, 'I think I got 4x = 16 for this');
        $this->assertSame("Correct.\nOn the step “Subtract 2 from both sides of 4x + 2 = 18”, read from your message: 4x = 16.", self::resultText($h), 'an equation_step answer read from a message');

        // The same forms from the line builder itself.
        $p = (new ProblemFactory(new ItemFactory()))->fromRecipe('TWO_STEP', ['a' => 2, 'b' => 3, 'c' => 11], 'CHECK');
        $this->assertSame('On 2x + 3 = 11 you answered 4.', TutorEngine::resultLine($p, '4', '4', false));
        $this->assertSame('On 2x + 3 = 11, read from your message: 4.', TutorEngine::resultLine($p, 'it is 4', '4', true));
    }

    // ------------------------------------------------------------ 13. small items

    /** §5.13 (§4.8): "one" as a determiner, the wider goal-claim detector, the SIGN_ERROR_B observation, factor-of-1, corrections during a session. */
    public function testTheSmallItems(): void
    {
        // 1. "one" in "here is one to try", "one more", "one of" is not the number 1 (logging only).
        foreach (['Here is one to try.', 'One more, then we stop.', 'Pick one of these.'] as $text) {
            $this->assertNotContains(1, Numbers::words($text), $text);
        }
        $this->assertContains(1, Numbers::words('x is one.'));
        $p = (new ProblemFactory(new ItemFactory()))->fromRecipe('ONE_STEP_ADD', ['a' => 4, 'b' => 5], 'CHECK'); // x = 1
        $this->assertSame('1', (string) $p['answer']['expected']);
        foreach (['Here is one to try.', 'Try one more.', 'Here is one of the steps.'] as $text) {
            $this->assertFalse(TextGuard::leaks($text, $p), $text);
        }
        $this->assertTrue(TextGuard::leaks('So x is one.', $p));

        // 2. The goal-claim detector catches "the goal for … is met" and "the … goal is met"; it still only logs.
        foreach (['The goal for solving two-step equations is met.', 'Great work: the two-step equations goal is met!', 'Your goal for Q06 has been reached.'] as $text) {
            $this->assertNotSame([], TextGuard::masteryClaims($text), $text);
        }
        $this->assertSame([], TextGuard::masteryClaims('Let us keep working toward the goal.'));

        // 3. The SIGN_ERROR_B observation.
        $q07 = (new ProblemFactory(new ItemFactory()))->fromRecipe('GROUPED_LINEAR', ['a' => 3, 'b' => 4, 'c' => 27], 'CHECK'); // x = 5; c/a + b = 13
        $sig = (new AnswerCheck(new ItemFactory()))->check($q07, '13')['signatures'];
        $this->assertSame('SIGN_ERROR_B', $sig[0]['code']);
        $this->assertSame('matches moving the constant to the other side without changing its sign', $sig[0]['label']);

        // 4. A factor-of-1 product is never slip-eligible (unchanged).
        $one = (new ProblemFactory(new ItemFactory()))->fromRecipe('POSITIVE_DISTRIBUTION', ['a' => 4, 'b' => 1], 'CHECK');
        $this->assertNull((new AnswerCheck(new ItemFactory()))->check($one, '4x + 5')['slip']);

        // 5. Corrections during an open session are unchanged: applied at once, the session stays open.
        $h = new Harness();
        $h->start('Q06');
        self::pose($h, self::item('check', 'Q06', 'linear_equation', '2x + 3 = 11', '4'));
        self::answer($h);
        $r = $h->app->engine->applyCorrection($h->sessionId(), $h->attempts()[0]['attempt_id'], 'verdict', 'INCORRECT', 'Reviewed.');
        $this->assertFalse($r['report_rebuilt'], 'no report yet: the session is open');
        $this->assertSame([0, 'INCORRECT'], [$h->goal('Q06')['count'], $h->attempts()[0]['verdict']]);
        $h->view['revision'] = $h->row()['revision'];
        self::say($h);
        $this->assertSame([], $h->eventsOf('SESSION_ENDED'), 'the session goes on');
    }
}
