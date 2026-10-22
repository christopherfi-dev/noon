<?php
declare(strict_types=1);

namespace AlgebraEngine\Tests\Integration;

use AlgebraEngine\Tests\Support\Harness;
use PHPUnit\Framework\TestCase;

/** Addendum §6 beta tests B1–B4, Round 2 test B10 and the per-skill start check. */
final class BetaJourneyTest extends TestCase
{
    private Harness $h;

    protected function setUp(): void
    {
        $this->h = new Harness();
    }

    protected function tearDown(): void
    {
        $this->h->cleanup();
    }

    /** B1: distribution signature → probe wrong → E06 → Socratic repair → E06 criterion → fresh Q07 ×3 variants → Q07 criterion → report. */
    public function testB1DistributionJourney(): void
    {
        $h = $this->h;
        $this->assertSame(200, $h->start('Q07')->status);
        $this->assertSame('TARGET_ASSESSMENT', $h->mode());
        $this->assertSame('GROUPED_LINEAR', $h->active()['template_id']);

        $h->submit($h->wrong('DIST_X_ONLY'));
        $this->assertSame('DIAGNOSTIC_PROBE', $h->mode());
        $obs = $h->events('OBSERVATION');
        $this->assertStringContainsString('matches distribution-to-x-only', $obs[0]['data']['statement']);
        $this->assertSame('OBSERVATION_NOT_CAUSE', $obs[0]['data']['status']);

        $h->submit($h->wrong('DIST_X_ONLY'));
        $this->assertSame('PREREQUISITE_CHECK', $h->mode());
        $this->assertSame('E06', $h->skill());
        $descent = $h->events('DESCENT');
        $this->assertSame(['Q07', 'E06'], [$descent[0]['data']['from_skill'], $descent[0]['data']['to_skill']]);
        $stack = $h->row()['snapshot']['return_stack'];
        $this->assertSame('Q07', $stack[0]['parent_skill_id']);

        $h->submit($h->wrong('FIRST_TERM_ONLY'));
        $this->assertSame('GUIDED_REPAIR', $h->mode());
        $this->assertSame('E06', $h->skill());
        $boundary = array_values(array_filter($h->events('BOUNDARY'), fn ($e) => $e['data']['reason'] === 'NOT_AVAILABLE_IN_BETA'));
        $this->assertSame('A07', $boundary[0]['data']['skill_id']);

        $this->assertStringContainsString('Your answer multiplied only the x by', implode(' ', array_column($h->view['messages'], 'text')), 'repair opens with what the answer did');

        // D1: a wrong guided step is answered with a smaller question, not the answer.
        $first = $h->active();
        $this->assertSame('GUIDED', $first['role']);
        $this->assertSame('ASSISTED', $h->view['active_item']['support_status']);
        $h->submit($h->plainWrong());
        $smaller = $h->active();
        $this->assertNotSame($first['guided_step']['id'], $smaller['guided_step']['id']);
        $this->assertStringStartsWith($first['guided_step']['id'] . '.', $smaller['guided_step']['id']);
        foreach ($h->view['messages'] as $m) {
            $this->assertStringNotContainsString($first['answer_spec']['expected'], $m['text'] === 'Not yet.' ? '' : $m['text']);
        }
        $this->assertStringEndsWith('?', trim(explode("\n", $smaller['prompt'])[0]));

        $h->completeGuidedRepair();
        $this->assertSame('INDEPENDENT_CHECK', $h->mode());
        $this->assertSame('E06', $h->skill());

        $variants = [];
        for ($i = 0; $i < 3; $i++) {
            $variants[] = $h->active()['variant_id'];
            $h->answerCorrect();
        }
        $this->assertSame(['constructed', 'contrast', 'transfer'], $variants);
        $this->assertSame('RETURN_TO_TARGET', $h->mode());
        $this->assertSame('Q07', $h->skill());
        $this->assertSame([], $h->row()['snapshot']['return_stack']);

        $variants = [];
        for ($i = 0; $i < 3; $i++) {
            $variants[] = $h->active()['variant_id'];
            $h->answerCorrect();
        }
        $this->assertSame(['constructed', 'contrast', 'transfer'], $variants);
        $this->assertSame('SESSION_REPORT', $h->mode());

        $met = $h->events('CRITERION_MET');
        $this->assertSame(['E06', 'Q07'], array_map(fn ($e) => $e['data']['skill_id'], $met));
        $this->assertSame(['IMMEDIATE_CRITERION_MET', 'IMMEDIATE_CRITERION_MET'], array_map(fn ($e) => $e['data']['status'], $met));

        $r = $h->request('GET', 'report');
        $this->assertSame(200, $r->status);
        $report = $r->body;
        $this->assertSame('COMPLETED', $report['report']['status']);
        $this->assertSame('MOCK (simulated model; no live provider was used)', $report['provider_label']);
        $this->assertStringContainsString('MOCK PROVIDER', $report['report']['policy_note']);
        $bySkill = array_column($report['per_skill'], null, 'skill_id');
        $this->assertSame('IMMEDIATE_CRITERION_MET', $bySkill['Q07']['criterion_status']);
        $this->assertSame('IMMEDIATE_CRITERION_MET', $bySkill['E06']['criterion_status']);
        $this->assertNotEmpty($bySkill['Q07']['error_signatures_observed']);
        $this->assertStringContainsString('not a confirmed cause', $bySkill['Q07']['error_signatures_observed'][0]);
        $this->assertContains('Q07 → E06', $report['descent_path']);
        $this->assertTrue((bool) array_filter($report['report']['unresolved_or_untested'], fn ($s) => str_contains($s, 'A07') && str_contains($s, 'not available in beta') && str_contains($s, 'not a learner failure')));
        $this->assertDoesNotMatchRegularExpression('/master/i', json_encode($report));
        $this->assertNotEmpty($report['report']['transfer_evidence']);
        $this->assertSame([], $report['report']['retention_evidence']);
    }

    /** B2: wrong Q07 → probe correct → no descent; streak remains broken. */
    public function testB2ProbeCorrectNoDescent(): void
    {
        $h = $this->h;
        $h->start('Q07');
        $h->answerCorrect();
        $this->assertSame(1, $h->view['progress']['independent_successes']);
        $h->submit($h->plainWrong());
        $this->assertSame('DIAGNOSTIC_PROBE', $h->mode());
        $this->assertSame(0, $h->view['progress']['independent_successes']);
        $h->answerCorrect();
        $this->assertSame('TARGET_ASSESSMENT', $h->mode());
        $this->assertSame([], $h->events('DESCENT'));
        $this->assertSame(0, $h->view['progress']['independent_successes'], 'probe success must not restore or advance the streak');
        $this->assertSame([], $h->row()['snapshot']['return_stack']);
        $codes = array_column(array_column($h->events('OBSERVATION'), 'data'), 'code');
        $this->assertContains('ERROR_NOT_REPRODUCED', $codes);
        foreach ($h->events('HYPOTHESIS') as $e) {
            $this->assertNotSame('CONTRADICTED', $e['data']['status']);
            $this->assertNotSame('SUPPORTED_FOR_OBSERVED_PATTERN', $e['data']['status']);
        }
    }

    /** B3: Q07 → Q06 → Q05 → N06 and back up; return stack preserved; each parent re-verified with fresh work. */
    public function testB3DeepDescentAndReturn(): void
    {
        $h = $this->h;
        $h->start('Q07');
        $h->submit($h->wrong('SIGN_ERROR_B'));
        $h->submit($h->wrong('SIGN_ERROR_B'));
        $this->assertSame(['PREREQUISITE_CHECK', 'Q06'], [$h->mode(), $h->skill()]);
        $h->submit($h->wrong('MULTIPLY_INSTEAD_OF_DIVIDE'));
        $this->assertSame(['PREREQUISITE_CHECK', 'Q05'], [$h->mode(), $h->skill()]);
        $h->submit($h->wrong('QUOTIENT_SIGN'));
        $this->assertSame(['PREREQUISITE_CHECK', 'N06'], [$h->mode(), $h->skill()]);
        $stack = array_column($h->row()['snapshot']['return_stack'], 'parent_skill_id');
        $this->assertSame(['Q07', 'Q06', 'Q05'], $stack);

        // An N06 error matching no pattern gives no branch: bounded repair at N06.
        $h->submit($h->wrong('NO_SIGNATURE'));
        $this->assertSame(['GUIDED_REPAIR', 'N06'], [$h->mode(), $h->skill()]);
        $this->assertSame(['Q07', 'Q06', 'Q05'], array_column($h->row()['snapshot']['return_stack'], 'parent_skill_id'));
        $h->completeGuidedRepair();
        $this->assertSame(3, $h->passChecksAt('N06'));
        $this->assertSame(['RETURN_TO_TARGET', 'Q05'], [$h->mode(), $h->skill()]);
        $h->answerCorrect();
        $this->assertSame(['RETURN_TO_TARGET', 'Q06'], [$h->mode(), $h->skill()]);
        $h->answerCorrect();
        $this->assertSame(['RETURN_TO_TARGET', 'Q07'], [$h->mode(), $h->skill()]);
        $this->assertSame([], $h->row()['snapshot']['return_stack']);
        $h->passChecksAt('Q07');
        $this->assertSame('SESSION_REPORT', $h->mode());

        $returns = array_map(fn ($e) => $e['data']['from_skill'] . '>' . $e['data']['to_skill'], $h->events('RETURN'));
        $this->assertSame(['N06>Q05', 'Q05>Q06', 'Q06>Q07'], $returns);
        // Each parent was retested with a fresh item presented after its return.
        $presented = $h->events('ITEM_PRESENTED');
        foreach (['Q05', 'Q06'] as $parent) {
            $fresh = array_filter($presented, fn ($e) => $e['data']['skill_id'] === $parent && $e['data']['mode'] === 'RETURN_TO_TARGET');
            $this->assertCount(1, $fresh, "$parent re-verified once with fresh work");
        }
        $reverified = array_filter($h->events('OBSERVATION'), fn ($e) => $e['data']['code'] === 'PARENT_REVERIFIED');
        $this->assertCount(2, $reverified);
        // A prerequisite success never advanced a parent's count.
        $crit = array_column($h->row()['snapshot']['criteria'], null, 'skill_id');
        $this->assertSame('COMPONENT_CRITERION_MET', $crit['N06']['immediate_status']);
        $this->assertSame('IMMEDIATE_CRITERION_MET', $crit['Q07']['immediate_status']);
    }

    /** B3 variant: N06 check succeeds independently, so descent stops and repair happens at Q05. */
    public function testB3PrerequisiteSuccessStopsDescent(): void
    {
        $h = $this->h;
        $h->start('Q07');
        $h->submit($h->wrong('SIGN_ERROR_B'));
        $h->submit($h->wrong('SIGN_ERROR_B'));
        $h->submit($h->wrong('MULTIPLY_INSTEAD_OF_DIVIDE'));
        $h->submit($h->wrong('QUOTIENT_SIGN'));
        $this->assertSame('N06', $h->skill());
        $h->answerCorrect();
        $this->assertSame(['GUIDED_REPAIR', 'Q05'], [$h->mode(), $h->skill()]);
        $this->assertSame(['Q07', 'Q06'], array_column($h->row()['snapshot']['return_stack'], 'parent_skill_id'));
    }

    /** B4: descent reaching A07 or Q03 → "not available in beta"; not reported as a learner failure. */
    public function testB4BetaBoundary(): void
    {
        $h = $this->h;
        $h->start('Q05');
        $h->submit($h->wrong('MULTIPLIED'));
        $this->assertSame('DIAGNOSTIC_PROBE', $h->mode());
        $h->submit($h->wrong('MULTIPLIED'));
        $this->assertSame(['GUIDED_REPAIR', 'Q05'], [$h->mode(), $h->skill()]);
        $b = $h->events('BOUNDARY')[0]['data'];
        $this->assertSame(['Q03', 'NOT_AVAILABLE_IN_BETA'], [$b['skill_id'], $b['reason']]);
        $this->assertStringContainsString('not a learner failure', $b['note']);
        $this->assertSame([], $h->row()['snapshot']['return_stack'], 'no frame is pushed for an out-of-beta skill');
        $h->event('END_SESSION');
        $report = $h->request('GET', 'report')->body;
        $text = json_encode($report);
        $this->assertStringNotContainsString('"skill_id":"Q03","title"', $text, 'Q03 is not reported as a touched/failed skill');
        $this->assertTrue((bool) array_filter($report['report']['unresolved_or_untested'], fn ($s) => str_starts_with($s, 'Q03') && str_contains($s, 'not a learner failure')));
        foreach ($report['per_skill'] as $p) {
            $this->assertNotSame('Q03', $p['skill_id']);
        }
    }

    /** Into guided repair at E06 on the B1 path (A07 boundary), at step s1. */
    private function intoE06Repair(): void
    {
        $h = $this->h;
        $h->start('Q07');
        $h->submit($h->wrong('DIST_X_ONLY'));
        $h->submit($h->wrong('DIST_X_ONLY'));
        $h->submit($h->wrong('FIRST_TERM_ONLY'));
        $this->assertSame(['GUIDED_REPAIR', 'E06', 's1'], [$h->mode(), $h->skill(), $h->active()['guided_step']['id']]);
    }

    /**
     * B10 (Round 2 ruling 8), case 1: stuck at the smallest guided question, whose skill is a declared
     * prerequisite in the beta → PREREQUISITE_CHECK on that skill directly, with no independent check
     * or probe in between; the interrupted parents survive and repair resumes at E06 afterwards.
     */
    public function testB10StuckStepGoesDirectlyToItsPrerequisite(): void
    {
        $h = $this->h;
        $this->intoE06Repair();
        $this->assertSame('N18', $h->active()['guided_step']['exercises']);
        $h->submit($h->plainWrong());
        $smallest = $h->active();
        $this->assertSame(['s1.1', 'N18'], [$smallest['guided_step']['id'], $smallest['guided_step']['exercises']]);
        $this->assertSame([], $smallest['guided_step']['smaller']);
        $this->assertNull($smallest['guided_step']['alt_prompt']);
        $transitions = count($h->events('TRANSITION'));
        $h->submit($h->plainWrong());

        $this->assertSame(['PREREQUISITE_CHECK', 'N18'], [$h->mode(), $h->skill()]);
        $new = array_slice($h->events('TRANSITION'), $transitions);
        $this->assertSame([['GUIDED_REPAIR', 'PREREQUISITE_CHECK']], array_map(fn ($e) => [$e['data']['from'], $e['data']['to']], $new), 'no independent check or probe first');
        $descent = $h->events('DESCENT');
        $last = end($descent)['data'];
        $this->assertSame(['E06', 'N18', 'STUCK_AT_SMALLEST_GUIDED_STEP'], [$last['from_skill'], $last['to_skill'], $last['trigger']]);
        $stack = $h->row()['snapshot']['return_stack'];
        $this->assertSame(['Q07', 'E06'], array_column($stack, 'parent_skill_id'), 'interrupted parents survive');
        $this->assertSame('GUIDED_REPAIR', $stack[1]['parent_mode']);
        $this->assertSame('PREREQUISITE', $h->active()['role']);
        foreach ($h->view['messages'] as $m) {
            $this->assertSame([], \AlgebraEngine\Provider\DisclosureGuard::violations($m['text'], [['answer' => $smallest['answer_spec'], 'variables' => ['x']]]), 'no answer shown');
        }
        // The policy edge exists only for this host trigger.
        $guard = new \AlgebraEngine\Workflow\TransitionGuard(ALGEBRA_APP_ROOT . '/private/resources/contracts/workflow-policy.json');
        $this->assertFalse($guard->allowed('GUIDED_REPAIR', 'PREREQUISITE_CHECK'));

        // Success at N18 returns to E06, where repair resumes with a fresh guided problem.
        $h->answerCorrect();
        $this->assertSame(['GUIDED_REPAIR', 'E06'], [$h->mode(), $h->skill()]);
        $this->assertSame(['Q07'], array_column($h->row()['snapshot']['return_stack'], 'parent_skill_id'));
    }

    /**
     * B10 (Round 2 ruling 8), case 2: the stuck question exercises a skill that is outside the beta
     * (A07) or is not a declared prerequisite (the repair skill itself) → the boundary is recorded as in
     * B4: not a learner failure, no frame pushed, reported as unresolved; repair continues at E06.
     */
    public function testB10StuckStepAtABoundaryIsRecordedNotFailed(): void
    {
        $h = $this->h;
        $this->intoE06Repair();
        $h->answerCorrect();                                   // s1
        $h->answerCorrect();                                   // s2
        $this->assertSame(['s3', 'E06'], [$h->active()['guided_step']['id'], $h->active()['guided_step']['exercises']]);
        $h->submit($h->plainWrong());                          // s3 → smaller s3.1
        $this->assertSame(['s3.1', 'A07'], [$h->active()['guided_step']['id'], $h->active()['guided_step']['exercises']]);
        $h->submit($h->plainWrong());                          // stuck at the smallest question: A07 is outside the beta

        $b = array_values(array_filter($h->events('BOUNDARY'), fn ($e) => $e['data']['from_skill'] === 'E06'));
        $last = end($b)['data'];
        $this->assertSame(['A07', 'NOT_AVAILABLE_IN_BETA'], [$last['skill_id'], $last['reason']]);
        $this->assertStringContainsString('not a learner failure', $last['note']);
        $this->assertSame(['GUIDED_REPAIR', 'E06'], [$h->mode(), $h->skill()]);
        $this->assertSame(['Q07'], array_column($h->row()['snapshot']['return_stack'], 'parent_skill_id'), 'no frame is pushed at a boundary');
        $this->assertSame('s1', $h->active()['guided_step']['id'], 'a new guided problem at E06');

        // Second guided problem: stuck at s3 itself, which exercises E06 (the repair skill), not a declared prerequisite.
        $h->answerCorrect();
        $h->answerCorrect();
        $h->submit($h->plainWrong());                          // s3 → s3.1
        $h->answerCorrect();                                   // s3.1 → back to s3
        $this->assertSame('s3', $h->active()['guided_step']['id']);
        $h->submit($h->plainWrong());                          // s3 → alternate phrasing
        $h->submit($h->plainWrong());                          // stuck at s3 with nothing smaller left
        $b = array_values(array_filter($h->events('BOUNDARY'), fn ($e) => $e['data']['reason'] === 'NOT_A_DECLARED_PREREQUISITE'));
        $this->assertCount(1, $b);
        $this->assertSame(['E06', 'E06'], [$b[0]['data']['skill_id'], $b[0]['data']['from_skill']]);
        $this->assertStringContainsString('not a learner failure', $b[0]['data']['note']);
        $this->assertSame(['GUIDED_REPAIR', 'E06'], [$h->mode(), $h->skill()]);
        $this->assertSame(['Q07'], array_column($h->row()['snapshot']['return_stack'], 'parent_skill_id'));
        $this->assertCount(2, $h->events('GUIDED_PROBLEM_ABANDONED'));
        $this->assertSame([], array_filter($h->events('DESCENT'), fn ($e) => ($e['data']['trigger'] ?? null) === 'STUCK_AT_SMALLEST_GUIDED_STEP'));

        // A third boundary uses up the guided-problem budget (3 per cycle): an independent check follows.
        $h->answerCorrect();
        $h->answerCorrect();
        $h->event('NOT_LEARNED');                              // s3 → s3.1
        $h->event('NOT_LEARNED');                              // stuck at s3.1 (A07) again
        $this->assertCount(3, $h->events('GUIDED_PROBLEM_ABANDONED'));
        $this->assertSame(['INDEPENDENT_CHECK', 'E06'], [$h->mode(), $h->skill()]);

        $h->event('END_SESSION');
        $report = $h->request('GET', 'report')->body;
        $unresolved = $report['report']['unresolved_or_untested'];
        $this->assertTrue((bool) array_filter($unresolved, fn ($s) => str_starts_with($s, 'A07') && str_contains($s, 'not a learner failure')));
        $this->assertTrue((bool) array_filter($unresolved, fn ($s) => str_contains($s, 'E06 itself') && str_contains($s, 'not a learner failure')));
        foreach ($report['per_skill'] as $p) {
            $this->assertNotSame('A07', $p['skill_id']);
        }
    }

    /**
     * Regression (round 3 gate): across many random B1 and B3 journeys with the simulated provider, every
     * session reaches the report without a HOLD. Two causes were found and fixed: an opening feedback fact
     * that matched a guided step answer, and a simulated model that re-proposed rejected numbers on repair.
     */
    public function testRandomScriptedJourneysCompleteWithoutHolds(): void
    {
        for ($i = 0; $i < 40; $i++) {
            $h = new Harness([], null, null, null, null, "journey$i");
            try {
                $h->start('Q07');
                $wrong = $i % 2 ? ['DIST_X_ONLY', 'DIST_X_ONLY', 'FIRST_TERM_ONLY'] : ['SIGN_ERROR_B', 'SIGN_ERROR_B', 'MULTIPLY_INSTEAD_OF_DIVIDE', 'QUOTIENT_SIGN', 'NO_SIGNATURE'];
                foreach ($wrong as $code) {
                    $h->submit($h->wrong($code));
                }
                for ($n = 0; $n < 40 && $h->view['status'] === 'READY' && $h->mode() !== 'SESSION_REPORT'; $n++) {
                    $h->answerCorrect();
                }
                $this->assertSame(['READY', 'SESSION_REPORT'], [$h->view['status'], $h->mode()], "seed journey$i: " . json_encode(array_slice(array_column(array_column($h->events('MODEL_OUTPUT_REJECTED'), 'data'), 'errors'), -3)));
            } finally {
                $h->cleanup();
            }
        }
    }

    /**
     * B12 (round 4, R4-A): a re-ask of a previously failed guided step shows the host line and the item only.
     * No model call is made and no model-written text is shown, both after a smaller question and for the
     * alternate phrasing.
     */
    public function testB12ReAskShowsNoModelText(): void
    {
        $h = $this->h;
        $this->intoE06Repair();
        $reAsk = function (string $stepId) use ($h): void {
            $calls = $h->mock->calls;
            $before = count($h->events());
            $r = $h->answerCorrect();                                  // the smaller question is solved → re-ask
            $this->assertSame($stepId, $h->active()['guided_step']['id']);
            $this->assertSame([['FEEDBACK', 'Yes, that is right.'], ['INFORMATION', 'Now try the earlier question again.']],
                array_map(fn ($m) => [$m['kind'], $m['text']], $r->body['messages']), 'host line and item only');
            $this->assertSame($calls, $h->mock->calls, 'no model call for the re-ask');
            $new = array_slice($h->events(), $before);
            $this->assertSame([], array_values(array_filter($new, fn ($e) => in_array($e['type'], ['MODEL_OUTPUT_ACCEPTED', 'MODEL_OUTPUT_REJECTED'], true))));
            $this->assertCount(1, array_filter($new, fn ($e) => $e['type'] === 'HOST_ONLY_STEP'));
        };
        $h->submit($h->plainWrong());                                  // s1 wrong → smaller s1.1
        $reAsk('s1');
        $h->answerCorrect();                                           // s1
        $h->answerCorrect();                                           // s2
        $h->submit($h->plainWrong());                                  // s3 wrong → smaller s3.1
        $reAsk('s3');

        // Alternate phrasing after failing the re-asked step: also host-only.
        $calls = $h->mock->calls;
        $r = $h->submit($h->plainWrong());
        $this->assertSame('alt', $h->active()['params']['phrasing']);
        foreach ($r->body['messages'] as $m) {
            $this->assertContains($m['text'], ['Not yet.', 'Here is the same question, asked another way.'], 'no model text: ' . $m['text']);
        }
        $this->assertSame($calls, $h->mock->calls);
    }

    /**
     * B13 (round 4, R4-B): for each N06 sign pattern, a failed item produces guided items with the same pattern:
     * the first guided problem and the first fresh guided problem after it. N06 items always have a negative
     * operand (item specification), so the patterns are + ÷ −, − ÷ + and − ÷ −.
     */
    public function testB13RepairKeepsTheFailedSignPattern(): void
    {
        $sign = fn (int $v) => $v > 0 ? '+' : '-';
        foreach ([[72, -9, 40, -8], [-72, 9, -40, 8], [-72, -9, -40, -8]] as [$a1, $b1, $a2, $b2]) {
            $pattern = $sign($a1) . $sign($b1);
            $h = new Harness([], null, null, null, null, 'b13' . $pattern);
            try {
                $h->mock->faults[] = ['type' => 'ITEM_PATCH', 'patch' => ['parameters' => ['a' => $a1, 'b' => $b1]]];
                $h->start('N06');
                $this->assertSame(['a' => $a1, 'b' => $b1], $h->active()['params']);
                $h->mock->faults[] = ['type' => 'ITEM_PATCH', 'patch' => ['parameters' => ['a' => $a2, 'b' => $b2]]];
                $h->submit($h->wrong('NO_SIGNATURE'));                  // target wrong → slip-check probe, same pattern
                $this->assertSame('DIAGNOSTIC_PROBE', $h->mode());
                $h->submit($h->wrong('NO_SIGNATURE'));                  // probe wrong, no signature → repair at N06
                $this->assertSame(['GUIDED_REPAIR', 'N06'], [$h->mode(), $h->skill()], $pattern);
                $started = $h->events('REPAIR_STARTED');
                $this->assertSame(['sign_a' => $sign($a1), 'sign_b' => $sign($b1)], end($started)['data']['kept_features'], $pattern);
                $plans = [];
                for ($n = 0; $n < 30 && $h->mode() === 'GUIDED_REPAIR' && count($plans) < 2; $n++) {
                    $p = $h->active()['guided_plan_params'];
                    $plans[json_encode($p)] = $sign($p['a']) . $sign($p['b']);
                    $h->answerCorrect();
                }
                $this->assertSame([$pattern, $pattern], array_values($plans), "first guided problem and the next fresh one keep $pattern");
            } finally {
                $h->cleanup();
            }
        }
    }

    /** Every beta skill can be started, presents a host-accepted item and scores a correct answer. */
    public function testEveryBetaSkillCanStartAndScore(): void
    {
        foreach (['Q07', 'Q06', 'Q05', 'Q04', 'E06', 'N18', 'N03', 'N04', 'N05', 'N06'] as $skill) {
            $h = new Harness();
            $this->assertSame(200, $h->start($skill)->status, $skill);
            $this->assertSame($skill, $h->active()['skill_id']);
            $h->answerCorrect();
            $this->assertSame(1, $h->view['progress']['independent_successes'], $skill);
            $h->cleanup();
        }
    }
}
