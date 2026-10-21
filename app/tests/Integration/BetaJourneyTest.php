<?php
declare(strict_types=1);

namespace AlgebraEngine\Tests\Integration;

use AlgebraEngine\Tests\Support\Harness;
use PHPUnit\Framework\TestCase;

/** Addendum §6 beta tests B1–B4 and the per-skill start check. */
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
