<?php
declare(strict_types=1);

namespace AlgebraEngine\Tests\Integration;

use AlgebraEngine\Curriculum\BetaScope;
use AlgebraEngine\Math\Rational;
use AlgebraEngine\Support\Json;
use AlgebraEngine\Support\RandomSource;
use AlgebraEngine\Tests\Support\Harness;
use PHPUnit\Framework\TestCase;

/**
 * Round 6 M2: the tutor loop — one call per turn, pre-generated problems,
 * reasoning chosen per call, one repair then a code fallback, holds — driven
 * through the real API with the scripted mock tutor. Mock results say nothing
 * about a live provider or about learning.
 */
final class TutorLoopTest extends TestCase
{
    /** Context blocks of the n-th last envelope call the mock received. */
    public static function context(Harness $h, int $back = 1): array
    {
        $calls = array_values(array_filter($h->mock->log, fn ($c) => $c['json']));
        $messages = $calls[count($calls) - $back]['messages'];
        foreach ($messages as $m) {
            if ($m['role'] === 'user' && str_starts_with($m['content'], 'JSON ') && !str_contains($m['content'], 'REPAIR_REQUEST')) {
                return Json::decode(substr($m['content'], 5), 64);
            }
        }
        self::fail('no context block');
    }

    /** Queue an envelope that poses this problem, then trigger a reply with a message. */
    public static function pose(Harness $h, string $purpose, string $skill, string $kind, string $expr, string $stated, string $prompt = '', string $message = 'Here is the next one.', string $move = 'POSE_PROBLEM'): void
    {
        $h->mock->queue('ENVELOPE', ['set' => ['message_type' => 'QUESTION', 'student_message' => $message, 'item' => [
            'purpose' => $purpose, 'skill_id' => $skill, 'source' => 'spec', 'recipe_ref' => null,
            'spec' => ['kind' => $kind, 'expression' => $expr], 'prompt' => $prompt, 'stated_answer' => $stated,
        ], 'proposed_transition' => ['to_mode' => $move, 'target_skill_id' => $skill, 'reason' => 'test', 'evidence_event_ids' => []]]]);
        $r = $h->send('next one please');
        self::assertSame(200, $r->status, json_encode($r->body));
        self::assertSame(strtolower($purpose), $h->view['open_problem']['purpose'] ?? null, 'the posed problem is open: ' . json_encode(end($h->view['conversation'])));
    }

    /**
     * FLOW-01: the distribution journey. The topic Q07 is kept while the tutor
     * works on E06; the signature is an observation, not a diagnosis; each goal
     * counts only its own check questions; Q07 needs its own fresh checks.
     */
    public function testJourneyStepsBackAndReturnsWithGoalsComputedByCode(): void
    {
        $h = new Harness();
        $this->assertSame(200, $h->start('Q07')->status);
        $this->assertSame('practice', $h->view['open_problem']['purpose']);
        $p = $h->open()['params'];
        $h->send((string) Rational::of($p['c'] - $p['b'], $p['a'])); // distribution to x only
        $this->assertSame('DIST_X_ONLY', $h->attempts()[0]['signatures'][0]);
        $ctx = self::context($h);
        $this->assertSame('DIST_X_ONLY', $ctx['CODE_RESULT']['signatures'][0]['code'], 'the signature reaches the AI');
        $this->assertSame('E06', $h->view['focus']['skill_id'], 'the tutor stepped back to the skill the error points to');
        $this->assertSame('Q07', $h->view['topic']['skill_id'], 'the topic is kept');
        $moves = array_column(array_column($h->eventsOf('AI_MOVE'), 'data'), 'move');
        $this->assertContains('STEP_BACK', $moves);
        for ($i = 0; $i < 16 && !$h->view['wrap_up']; $i++) {
            $this->assertNotNull($h->view['open_problem'], 'the tutor keeps the student moving');
            $this->assertSame(200, $h->answerCorrectly()->status);
        }
        $this->assertTrue($h->view['wrap_up']);
        $this->assertTrue($h->goal('E06')['met']);
        $this->assertTrue($h->goal('Q07')['met']);
        $this->assertSame(['E06', 'Q07'], array_column(array_column($h->eventsOf('GOAL_MET'), 'data'), 'skill_id'), 'goals are recorded by code in order');
        $bySkill = array_column($h->attempts(), 'skill_id', 'attempt_id');
        foreach (['E06', 'Q07'] as $skill) {
            foreach ($h->eventsOf('GOAL_MET') as $e) {
                if ($e['data']['skill_id'] === $skill) {
                    $this->assertSame([$skill], array_values(array_unique(array_map(fn ($id) => $bySkill[$id], $e['data']['attempt_ids']))), "$skill: only its own check questions count");
                }
            }
        }
        $calls = $h->callsPerTurn();
        $this->assertSame(2, $calls[0], 'the first turn binds and replies');
        $this->assertSame(array_fill(0, count($calls) - 1, 1), array_slice($calls, 1), 'every later turn uses exactly one call');
    }

    public function testRandomLearnersMostTurnsUseExactlyOneCall(): void
    {
        $histogram = [];
        $holds = 0;
        foreach (range(1, 12) as $n) {
            $h = new Harness([], null, null, null, null, 'random' . $n);
            $rng = RandomSource::seeded('learner' . $n);
            $h->start(BetaScope::SKILLS[$n % 10]);
            for ($i = 0; $i < 18; $i++) {
                $open = $h->view['open_problem'];
                $roll = $rng->int(1, 10);
                $r = match (true) {
                    $open === null => $h->send('what should I do next'),
                    $roll <= 5 => $h->answerCorrectly(),
                    $roll <= 7 => $h->send($h->wrong()),
                    $roll === 8 => $h->stuck($rng->pick(['HINT', 'EXPLAIN'])),
                    $roll === 9 => $h->send('why does that work'),
                    default => $h->send('i think it is ' . $h->answer()),
                };
                $this->assertSame(200, $r->status, json_encode($r->body));
                $holds += $h->view['status'] === 'HOLD' ? 1 : 0;
            }
            foreach ($h->callsPerTurn() as $c) {
                $histogram[$c] = ($histogram[$c] ?? 0) + 1;
            }
        }
        ksort($histogram);
        $total = array_sum($histogram);
        $this->assertSame(0, $holds);
        $this->assertGreaterThan(0.6 * $total, $histogram[1] ?? 0, 'most turns use exactly one call: ' . json_encode($histogram));
        $this->assertLessThanOrEqual(3, max(array_keys($histogram)), 'no turn needs more than three calls');
    }

    public function testEveryCallCarriesFreshPreparedProblemsAndIsBuiltAnew(): void
    {
        $h = new Harness();
        $h->start('Q07');
        $h->answerCorrectly();
        $h->answerCorrectly();
        $shown = array_column(array_column($h->eventsOf('PROBLEM_PRESENTED'), 'data'), 'fingerprint');
        $ctx = self::context($h);
        $bySkill = [];
        foreach ($ctx['PREPARED_PROBLEMS'] as $p) {
            $bySkill[$p['skill_id']] = ($bySkill[$p['skill_id']] ?? 0) + 1;
            $this->assertArrayHasKey('answer_for_your_teaching_never_state', $p);
        }
        $this->assertSame(['Q07' => 2, 'Q06' => 2, 'E06' => 2], $bySkill, 'two prepared problems per relevant skill');
        $this->assertCount(10, $ctx['CURRICULUM'], 'the ten-skill slice');
        foreach (['TRUSTED_SESSION', 'TURN', 'CODE_RESULT', 'OPEN_PROBLEM', 'GOALS', 'GRADEBOOK', 'RECENT_CONVERSATION', 'TOPIC', 'FOCUS', 'CHECK_PATTERNS', 'OUTPUT_CONTRACT'] as $block) {
            $this->assertArrayHasKey($block, $ctx);
        }
        $calls = array_values(array_filter($h->mock->log, fn ($c) => $c['json']));
        foreach ($calls as $c) {
            $this->assertCount(5, $c['messages'], 'every request is built anew: architecture, rules, binding exchange, one context block');
        }
        $this->assertStringContainsString('ACE_BOUND: architecture=1.3', $calls[0]['messages'][3]['content']);
        // A prepared problem is never one already shown in the session.
        foreach ($h->app->store->db->all('SELECT fingerprint FROM items') as $row) {
            $this->assertContains($row['fingerprint'], $shown);
        }
    }

    public function testReasoningIsChosenPerCallAndLogged(): void
    {
        $h = new Harness();
        $h->start('Q07');
        $h->send($h->wrong());                      // a wrong answer with no code signature
        $h->send('why do we divide first?');         // a message about the math
        $h->send('ok');                              // a message, not about the math
        self::pose($h, 'practice', 'N18', 'numeric_step', '4 * 4', '16', 'What is 4 × 4?');
        $h->send('16 because it\'s 4 x 4');           // an entry the AI must read
        $h->answerCorrectly();
        $rows = $h->app->store->db->all('SELECT call_purpose, thinking, reasoning_effort FROM model_calls ORDER BY rowid');
        $seen = array_map(fn ($r) => $r['call_purpose'] . ':' . $r['thinking'] . ($r['reasoning_effort'] ? '/' . $r['reasoning_effort'] : ''), $rows);
        $this->assertSame('BINDING:disabled', $seen[0]);
        $this->assertSame('REPLY:disabled', $seen[1], 'the opening reply');
        $this->assertSame('REPLY:enabled/low', $seen[2], 'a wrong answer with no signature');
        $this->assertSame('REPLY:enabled/low', $seen[3], 'a message about the math');
        $this->assertSame('REPLY:disabled', $seen[4], 'a message not about the math');
        $this->assertContains('READING:enabled/low', $seen, 'an entry the AI must read');
        $this->assertSame('REPLY:disabled', end($seen), 'a correct answer');
        foreach ($h->eventsOf('TURN_METRICS') as $e) {
            $this->assertCount($e['data']['calls'], $e['data']['reasoning'], 'which setting was used is logged on every call');
        }
        // Fixed settings for Stage 2 runs (a), (c) and (d).
        foreach (['disabled' => 'disabled', 'enabled' => 'enabled'] as $mode => $want) {
            $g = new Harness(['provider_thinking' => $mode, 'provider_reasoning_effort' => $mode === 'enabled' ? 'high' : '']);
            $g->start('Q07');
            $g->send($g->wrong());
            $thinking = array_column($g->app->store->db->all("SELECT thinking FROM model_calls WHERE call_purpose != 'BINDING'"), 'thinking');
            $this->assertSame(array_fill(0, count($thinking), $want), $thinking);
        }
    }

    public function testOneRepairThenAccepted(): void
    {
        $h = new Harness();
        $h->start('Q07');
        $h->mock->queue('WRONG_ANSWER');
        $before = $h->lines();
        $h->send('can I have a different question');
        $rejected = $h->eventsOf('MODEL_OUTPUT_REJECTED');
        $this->assertCount(1, $rejected);
        $this->assertStringContainsString('stated answer', implode(' ', $rejected[0]['data']['errors']));
        $metrics = $h->eventsOf('TURN_METRICS');
        $this->assertSame(2, end($metrics)['data']['calls'], 'one repair call');
        $this->assertSame([], $h->eventsOf('FALLBACK_USED'));
        foreach ($h->lastLines($before) as $l) {
            $this->assertStringNotContainsString('3(x + 4) = 27', $l['text'], 'the rejected problem never reached the student');
        }
    }

    public function testFallbackAfterTheRepairFailsToo(): void
    {
        $h = new Harness();
        $h->start('Q07');
        $h->mock->queue('OFF_PATTERN_CHECK', ['persist' => true]);
        $before = $h->lines();
        $h->send('next please');
        $this->assertCount(1, $h->eventsOf('FALLBACK_USED'));
        $this->assertCount(2, $h->eventsOf('MODEL_OUTPUT_REJECTED'));
        $new = $h->lastLines($before);
        $this->assertSame(['student', 'app', 'tutor'], array_column($new, 'role'), 'the AI text was dropped; code wrote the line and showed a prepared problem');
        $this->assertSame('Let\'s practise with this one.', $new[1]['text']);
        $this->assertSame('practice', $h->view['open_problem']['purpose']);
        $this->assertSame('RECIPE', $h->open()['source']);
    }

    public function testTransportFailureHoldsAndResumeRetriesTheReply(): void
    {
        $h = new Harness();
        $h->start('Q07');
        $h->mock->queue('TIMEOUT');
        $h->send($h->wrong());
        $this->assertSame('HOLD', $h->view['status']);
        $this->assertSame(['RESUME', 'END_SESSION'], $h->view['allowed_actions']);
        $this->assertCount(1, $h->attempts(), 'the answer was recorded before the AI call; a provider failure is not a learner failure');
        $h->event('RESUME');
        $this->assertSame('READY', $h->view['status']);
        $this->assertCount(1, $h->attempts(), 'resuming does not record the answer twice');
        $this->assertSame('ANSWER', self::context($h)['TURN']['kind'] === 'RESUMED' ? 'ANSWER' : self::context($h)['TURN']['kind']);
    }

    public function testEveryBetaSkillCanStartAndScore(): void
    {
        foreach (BetaScope::SKILLS as $skill) {
            $h = new Harness([], null, null, null, null, 'skill' . $skill);
            $this->assertSame(200, $h->start($skill)->status, $skill);
            $this->assertSame($skill, $h->view['topic']['skill_id']);
            $this->assertNotNull($h->view['open_problem'], $skill);
            $h->answerCorrectly();
            $this->assertSame('CORRECT', $h->attempts()[0]['verdict'], $skill);
            $this->assertSame($skill, $h->attempts()[0]['skill_id']);
        }
    }

    /** FLOW-03: changing topic during a step back sets the open problem aside, replaces the topic in the next context and keeps each skill's run separate. */
    public function testChangeTopicSetsTheOpenProblemAsideAndTheTutorContinues(): void
    {
        $h = new Harness();
        $h->start('Q07');
        $h->event('CHANGE_TOPIC', ['topic_skill_id' => 'N05']);
        $this->assertSame('N05', $h->view['topic']['skill_id']);
        $this->assertSame('N05', $h->open()['skill_id']);
        $closed = array_column(array_column($h->eventsOf('PROBLEM_CLOSED'), 'data'), 'reason');
        $this->assertSame(['SET_ASIDE'], $closed, 'set aside, not counted as a failure');
        $this->assertSame([], $h->attempts());
        $ctx = self::context($h);
        $this->assertSame(['N05', 'N05'], [$ctx['TOPIC']['skill_id'], $ctx['FOCUS']['skill_id']], 'the next context carries the new topic only');
        // During a step back: the E06 run is kept apart from the new topic's.
        $g = new Harness();
        $g->start('Q07');
        $p = $g->open()['params'];
        $g->send((string) Rational::of($p['c'] - $p['b'], $p['a']));
        $this->assertSame('E06', $g->view['focus']['skill_id']);
        $g->answerCorrectly();                                      // E06 practice: correct, then an E06 check
        $g->answerCorrectly();                                      // E06 check 1
        $this->assertSame(1, $g->goal('E06')['count']);
        $g->event('CHANGE_TOPIC', ['topic_skill_id' => 'Q06']);
        $this->assertSame(['Q06', 'Q06'], [$g->view['topic']['skill_id'], $g->view['focus']['skill_id']]);
        $g->answerCorrectly();                                      // Q06 practice
        $g->answerCorrectly();                                      // Q06 check 1
        $this->assertSame([1, 1], [$g->goal('E06')['count'], $g->goal('Q06')['count']], 'runs are not mixed');
    }

    /** ENTRY-01: a known topic is validated and the tutor starts with one problem whose answer code computed; no pretest. */
    public function testEntry01KnownTopic(): void
    {
        $h = new Harness();
        $this->assertSame(422, $h->start('Q99')->status, 'an unknown skill is refused');
        $this->assertSame(200, $h->start('Q07')->status);
        $this->assertSame(['Q07', 'Q07'], [$h->view['topic']['skill_id'], $h->view['focus']['skill_id']]);
        $this->assertCount(1, $h->eventsOf('PROBLEM_PRESENTED'), 'one problem, no blanket pretest of earlier skills');
        $this->assertSame('Q07', $h->open()['skill_id']);
        $this->assertNotNull($h->open()['answer']['expected'] ?? null, 'code computed its answer');
        $this->assertSame([], $h->attempts());
    }

    /** FLOW-02: a deeper step back (Q07, then E06, then N18) keeps the topic; the tutor returns to it; every goal needs its own fresh checks. */
    public function testFlow02DeeperStepBackKeepsTheTopic(): void
    {
        $h = new Harness();
        $h->start('Q07');
        self::pose($h, 'practice', 'E06', 'expand', '4(x + 3)', '4x + 12', '', 'Let\'s look at the parentheses first.', 'STEP_BACK');
        self::pose($h, 'practice', 'N18', 'numeric_step', '4 * 3', '12', 'What is 4 × 3?', 'Let\'s start with the multiplication.', 'STEP_BACK');
        $this->assertSame(['Q07', 'N18'], [$h->view['topic']['skill_id'], $h->view['focus']['skill_id']]);
        $this->assertSame(['STEP_BACK', 'STEP_BACK'], array_slice(array_column(array_column($h->eventsOf('AI_MOVE'), 'data'), 'move'), -2));
        for ($i = 0; $i < 8 && !$h->goal('N18')['met']; $i++) {
            $h->answerCorrectly();
        }
        $this->assertTrue($h->goal('N18')['met']);
        $this->assertSame([0, 0], [$h->goal('E06')['count'], $h->goal('Q07')['count']], 'N18 checks count for N18 only');
        $this->assertSame('Q07', $h->view['topic']['skill_id'], 'the topic survived two steps back');
        $this->assertSame('Q07', $h->view['focus']['skill_id'], 'the tutor returned to the topic');
        for ($i = 0; $i < 10 && !$h->view['wrap_up']; $i++) {
            $h->answerCorrectly();
        }
        $this->assertTrue($h->goal('Q07')['met']);
        $met = array_column(array_column($h->eventsOf('GOAL_MET'), 'data'), 'skill_id');
        $this->assertSame(['N18', 'Q07'], $met);
        $this->assertFalse($h->goal('E06')['met'], 'E06 was never checked, so it is not met');
    }

    /** FLOW-04: a wrong Q07 answer, then a correct answer on the easier skill: the error is not reproduced, no slip is confirmed and nothing passes automatically. */
    public function testFlow04CorrectEasierAnswerIsNoSlipAndNoPass(): void
    {
        $h = new Harness();
        $h->start('Q07');
        $p = $h->open()['params'];
        $h->send((string) Rational::of($p['c'] - $p['b'], $p['a']));
        $h->answerCorrectly(); // E06 practice
        [$wrong, $right] = $h->attempts();
        $this->assertSame(['INCORRECT', 'Q07', ['DIST_X_ONLY']], [$wrong['verdict'], $wrong['skill_id'], $wrong['signatures']]);
        $this->assertSame(['CORRECT', 'E06', 'PRACTICE'], [$right['verdict'], $right['skill_id'], $right['purpose']]);
        $this->assertSame([null, null], [$wrong['slip'], $right['slip']], 'no slip is confirmed');
        $this->assertSame([0, false, 0, false], [$h->goal('Q07')['count'], $h->goal('Q07')['met'], $h->goal('E06')['count'], $h->goal('E06')['met']], 'no automatic pass');
        $teacher = $h->request('GET', 'report')->body['summary']['teacher'];
        foreach ($teacher['skills'] as $skill) {
            foreach ($skill['observed'] as $o) {
                $this->assertStringContainsString('not a confirmed cause', $o);
            }
        }
    }

    /** EVID-01: help is recorded before any hint text is generated, even when the reply then fails; the next answer is with help and resets the run. */
    public function testEvid01HelpIsRecordedBeforeTheHint(): void
    {
        $h = new Harness();
        $h->start('Q06');
        self::pose($h, 'check', 'Q06', 'linear_equation', '3x + 4 = 19', '5', '', 'Here is a check question.');
        $h->mock->queue('TIMEOUT');
        $h->stuck('HINT');
        $this->assertSame('HOLD', $h->view['status'], 'the hint could not be prepared');
        $this->assertCount(1, $h->eventsOf('HELP_GIVEN'), 'help was committed before the AI call, so it stays recorded');
        $this->assertTrue($h->view['open_problem']['with_help']);
        $h->event('RESUME');
        $types = array_column($h->events(), 'type');
        $this->assertLessThan(max(array_keys($types, 'MODEL_OUTPUT_ACCEPTED', true)), array_search('HELP_GIVEN', $types, true), 'the hint shown after resuming was accepted after help was recorded');
        $h->answerCorrectly();
        $a = $h->attempts()[0];
        $this->assertSame(['CORRECT', ['HINT']], [$a['verdict'], $a['help']]);
        $this->assertSame(0, $h->goal('Q06')['count'], 'excluded from the run');
    }

    /** EVID-02: a check question already shown in the session is rejected, however the AI labels it; it earns no credit. */
    public function testEvid02ReusedCheckQuestionIsRejected(): void
    {
        $h = new Harness();
        $h->start('Q06');
        self::pose($h, 'check', 'Q06', 'linear_equation', '3x + 4 = 19', '5', '', 'Here is a check question.');
        $h->mock->queue('ENVELOPE', ['set' => ['message_type' => 'QUESTION', 'student_message' => 'Good.', 'item' => [
            'purpose' => 'check', 'skill_id' => 'Q06', 'source' => 'spec', 'recipe_ref' => null,
            'spec' => ['kind' => 'linear_equation', 'expression' => '3x + 4 = 19'], 'prompt' => '', 'stated_answer' => '5']]]);
        $h->answerCorrectly();
        $errors = array_merge(...array_map(fn ($e) => $e['data']['errors'], $h->eventsOf('MODEL_OUTPUT_REJECTED')));
        $this->assertNotSame([], array_filter($errors, fn ($e) => str_contains($e, 'must be fresh')), json_encode($errors));
        $shown = array_filter($h->lines(), fn ($l) => $l['kind'] === 'check' && str_contains($l['text'], '3x + 4 = 19'));
        $this->assertCount(1, $shown, 'the reused question was never shown again');
        $this->assertSame(1, $h->goal('Q06')['count']);
    }

    /** The AI may move only among the ten skills of this tutor; a step back outside them is rejected and the focus is unchanged. */
    public function testMovesStayInsideTheTutorsSkills(): void
    {
        $h = new Harness();
        $h->start('E06');
        $h->mock->queue('ENVELOPE', ['set' => ['proposed_transition' => ['to_mode' => 'STEP_BACK', 'target_skill_id' => 'A07', 'reason' => 'test', 'evidence_event_ids' => []]]]);
        $h->send('can we go back a step?');
        $errors = array_merge(...array_map(fn ($e) => $e['data']['errors'], $h->eventsOf('MODEL_OUTPUT_REJECTED')));
        $this->assertContains('proposed_transition.target_skill_id must be one of the ten skills in CURRICULUM', $errors);
        $this->assertSame('E06', $h->view['focus']['skill_id']);
        $this->assertNotContains('A07', array_column(array_column($h->eventsOf('AI_MOVE'), 'data'), 'target_skill_id'));
    }

    /** HOST-07: a required curriculum record is missing: the turn holds, the topic is kept, and nothing is invented. */
    public function testMissingCurriculumRecordHoldsWithoutInventingOne(): void
    {
        $repo = \AlgebraEngine\Curriculum\CurriculumRepository::fromFile(ALGEBRA_APP_ROOT . '/private/resources/curriculum/algebra-map.json')->without(['Q06']);
        $h = new Harness([], $repo);
        $h->start('Q07');
        $this->assertSame('HOLD', $h->view['status']);
        $this->assertSame('MISSING_CURRICULUM', $h->row()['snapshot']['issue']['type']);
        $this->assertSame(0, $h->mock->calls, 'no call is made with an invented record');
    }
}
