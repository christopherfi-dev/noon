<?php
declare(strict_types=1);

namespace AlgebraEngine\Tests\Integration;

use AlgebraEngine\Curriculum\CurriculumRepository;
use AlgebraEngine\Tests\Support\Harness;
use PHPUnit\Framework\TestCase;

/** Acceptance scenarios ENTRY-01, FLOW-01 … FLOW-07. */
final class FlowTest extends TestCase
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

    /** ENTRY-01: select Q07 → validated ID, a Q07 target episode with an accepted item, no blanket foundation pretest. */
    public function testEntry01KnownTopic(): void
    {
        $h = $this->h;
        $r = $h->start('Q07');
        $this->assertSame(200, $r->status);
        $this->assertSame(['skill_id' => 'Q07', 'title' => 'Solve equations containing parentheses'], $r->body['target']);
        $this->assertSame('TARGET_ASSESSMENT', $h->mode());
        $presented = $h->events('ITEM_PRESENTED');
        $this->assertCount(1, $presented);
        $this->assertSame(['Q07', 'TARGET'], [$presented[0]['data']['skill_id'], $presented[0]['data']['role']]);
        $this->assertCount(1, $h->events('EPISODE_STARTED'));
        $this->assertNotNull($h->row()['snapshot']['target_episode_id']);
        // Unknown and out-of-beta IDs are refused; nothing is created.
        $other = new Harness([], null, null, null, null);
        $other->bootstrap();
        $this->assertSame('UNKNOWN_SKILL', $other->start('Z99')->body['error_code']);
        $this->assertSame('SKILL_NOT_IN_BETA', $other->start('A07')->body['error_code']);
        $this->assertSame(0, (int) $other->app->store->db->scalar('SELECT COUNT(*) FROM sessions'));
        // The unsure and teacher routes are deferred in the beta and say so.
        $r = $other->request('POST', 'start', ['idempotency_key' => Harness::key(), 'expected_revision' => 0, 'entry_route' => 'UNSURE', 'target_skill_id' => null, 'task_token' => null, 'replace_current' => false]);
        $this->assertSame([422, 'FEATURE_NOT_IN_BETA'], [$r->status, $r->body['error_code']]);
        $other->cleanup();
    }

    /** FLOW-01: distribution journey keeps Q07 as parent, bounds the hypothesis, scopes the criterion and requires a fresh Q07 check. */
    public function testFlow01DistributionJourney(): void
    {
        $h = $this->h;
        $h->start('Q07');
        $h->submit($h->wrong('DIST_X_ONLY'), $this->expansionWork());
        $h->submit($h->wrong('DIST_X_ONLY'));
        $h->submit($h->wrong('FIRST_TERM_ONLY'));
        $this->assertSame('Q07', $h->row()['snapshot']['original_target_skill_id']);
        $this->assertSame('Q07', $h->row()['snapshot']['return_stack'][0]['parent_skill_id']);
        foreach ($h->events('HYPOTHESIS') as $e) {
            $this->assertContains($e['data']['status'], ['PROVISIONAL', 'SUPPORTED_FOR_OBSERVED_PATTERN']);
            $this->assertStringContainsString('Testable hypothesis only', $e['data']['statement']);
        }
        $h->completeGuidedRepair();
        $h->passChecksAt('E06');
        $crit = array_column($h->row()['snapshot']['criteria'], null, 'skill_id');
        $this->assertSame('IMMEDIATE_CRITERION_MET', $crit['E06']['immediate_status']);
        $this->assertSame('NOT_MET', $crit['Q07']['immediate_status'] ?? 'NOT_MET', 'E06 success does not pass Q07');
        $this->assertSame(['RETURN_TO_TARGET', 'Q07'], [$h->mode(), $h->skill()]);
        $this->assertSame('TARGET', $h->active()['role'], 'a fresh Q07 item is required');
    }

    private function expansionWork(): string
    {
        $p = $this->h->active()['params'];
        return $p['a'] . 'x + ' . $p['b'] . ' = ' . $p['c'];
    }

    /** FLOW-02: a declared prerequisite of the repair skill needs investigation → push frames, descend, unwind each parent with fresh evidence. */
    public function testFlow02DeeperRepair(): void
    {
        $h = $this->h;
        $h->start('Q07');
        $h->submit($h->wrong('SIGN_ERROR_B'));
        $h->submit($h->wrong('SIGN_ERROR_B'));
        $h->submit($h->wrong('MULTIPLY_INSTEAD_OF_DIVIDE'));
        $frames = $h->row()['snapshot']['return_stack'];
        $this->assertSame(['Q07', 'Q06'], array_column($frames, 'parent_skill_id'));
        foreach ($frames as $f) {
            $this->assertSame('all_of', $f['edge_type']);
            $this->assertNotEmpty($f['trigger_event_id']);
            $this->assertSame($h->row()['snapshot']['target_episode_id'], $f['target_episode_id']);
        }
        $this->assertSame(['DIAGNOSTIC_PROBE', 'PREREQUISITE_CHECK'], array_column($frames, 'parent_mode'));
        $this->assertSame('Q05', $h->skill());
        $h->answerCorrect(); // Q05 succeeds: repair at Q06
        $this->assertSame(['GUIDED_REPAIR', 'Q06'], [$h->mode(), $h->skill()]);
        $h->completeGuidedRepair();
        $this->assertSame(3, $h->passChecksAt('Q06'));
        $this->assertSame(['RETURN_TO_TARGET', 'Q07'], [$h->mode(), $h->skill()]);
        $this->assertSame([], $h->row()['snapshot']['return_stack']);
    }

    /** FLOW-03: switching topic during E06 repair suspends the episode, replaces the curriculum block and keeps streaks separate. */
    public function testFlow03SwitchTarget(): void
    {
        $h = $this->h;
        $h->start('Q07');
        $h->answerCorrect();
        $h->submit($h->plainWrong());
        $h->submit($h->wrong('DIST_X_ONLY'));
        $this->assertSame('E06', $h->skill());
        $oldEpisode = $h->row()['snapshot']['target_episode_id'];
        $oldStack = $h->row()['snapshot']['return_stack'];
        $h->event('CHANGE_TARGET', ['target_skill_id' => 'N05'], null, null, false);
        $snap = $h->row()['snapshot'];
        $this->assertSame(['TARGET_ASSESSMENT', 'N05', 'N05'], [$snap['mode'], $snap['original_target_skill_id'], $snap['current_skill_id']]);
        $this->assertNotSame($oldEpisode, $snap['target_episode_id']);
        $this->assertSame([$oldEpisode], $snap['suspended_episode_ids']);
        $this->assertSame([], $snap['return_stack']);
        $suspended = $h->events('EPISODE_SUSPENDED')[0]['data'];
        $this->assertSame($oldStack, $suspended['return_stack'], 'interrupted stack preserved');
        $this->assertSame('E06', $suspended['current_skill_id']);
        $h->answerCorrect();
        $byEpisode = [];
        foreach ($h->row()['snapshot']['criteria'] as $c) {
            $byEpisode[$c['target_episode_id']][$c['skill_id']] = $c['independent_correct_streak'];
        }
        $this->assertSame(1, $byEpisode[$snap['target_episode_id']]['N05']);
        $this->assertArrayNotHasKey('N05', $byEpisode[$oldEpisode] ?? []);
        // Returning to Q07 starts a new episode; the old streak is not reused.
        $h->event('CHANGE_TARGET', ['target_skill_id' => 'Q07'], null, null, false);
        $this->assertSame(0, $h->view['progress']['independent_successes']);
        $report = $h->request('GET', 'report')->body['report'];
        $this->assertTrue((bool) array_filter($report['unresolved_or_untested'], fn ($s) => str_contains($s, 'interrupted') && str_contains($s, 'not a failure')));
    }

    /** FLOW-04: wrong target answer then a correct probe → error not reproduced; no confirmed slip, no automatic pass. */
    public function testFlow04SingleCorrectProbe(): void
    {
        $h = $this->h;
        $h->start('Q07');
        $h->submit($h->wrong('DIST_X_ONLY'));
        $h->answerCorrect();
        $obs = array_values(array_filter($h->events('OBSERVATION'), fn ($e) => $e['data']['code'] === 'ERROR_NOT_REPRODUCED'));
        $this->assertCount(1, $obs);
        $this->assertStringContainsString('does not confirm a slip', $obs[0]['data']['statement']);
        $this->assertSame('TARGET_ASSESSMENT', $h->mode());
        $this->assertSame([], $h->events('CRITERION_MET'));
        $this->assertSame(0, $h->view['progress']['independent_successes']);
    }

    /** FLOW-05: alternative paths are selected by item features, never required jointly; conditions apply only when matched. */
    public function testFlow05AlternativeDependencies(): void
    {
        $c = CurriculumRepository::fromFile(ALGEBRA_REPO_ROOT . '/curriculum/algebra-map.json');
        $fraction = $c->resolvePrerequisites('Q10', ['path:fractions', 'fractional-coefficient variant']);
        sort($fraction['required']);
        $this->assertSame(['N09', 'N10', 'Q06'], $fraction['required']);
        $this->assertSame('fractions', $fraction['selected_path']);
        $this->assertSame(['decimals'], $fraction['unselected_paths']);
        $decimal = $c->resolvePrerequisites('Q10', ['path:decimals', 'decimal-coefficient variant']);
        sort($decimal['required']);
        $this->assertSame(['N11', 'Q06'], $decimal['required']);
        $plain = $c->resolvePrerequisites('Q10', []);
        $this->assertSame(['Q06'], $plain['required'], 'no alternative is required jointly');
        // Conditional edges of a beta skill apply only when the item has the feature.
        $this->assertSame(['N02', 'N17'], $c->resolvePrerequisites('N03', [])['required']);
        $this->assertContains('N09', $c->resolvePrerequisites('N03', ['item uses signed fractions'])['required']);
        $this->assertSame(['edge_type' => 'alternative', 'edge_condition' => 'path:fractions'], $c->edge('Q10', 'N09'));
    }

    /** FLOW-06: three failed repair-check cycles after intervening instruction → pause/route with scoped observations. */
    public function testFlow06BudgetBoundary(): void
    {
        $h = $this->h;
        $h->start('N03');
        $h->submit($h->plainWrong());
        $h->submit($h->plainWrong());
        for ($cycle = 1; $cycle <= 3; $cycle++) {
            $this->assertSame('GUIDED_REPAIR', $h->mode(), "cycle $cycle starts with instruction");
            $h->completeGuidedRepair();
            $this->assertSame('INDEPENDENT_CHECK', $h->mode());
            $h->submit($h->plainWrong());
            if ($cycle < 3) {
                $this->assertSame('DIAGNOSTIC_PROBE', $h->mode());
                $h->submit($h->plainWrong());
            }
        }
        $this->assertSame('HOLD', $h->view['status']);
        $issue = $h->row()['snapshot']['issue'];
        $this->assertSame('REPAIR_LIMIT_REACHED', $issue['type']);
        $this->assertStringContainsString('no foundational incapacity is inferred', $issue['detail']);
        $this->assertSame(['CHANGE_TARGET', 'END_SESSION'], $h->view['allowed_actions']);
        $this->assertCount(3, $h->events('REPAIR_CYCLE_FAILED'));
        $h->event('END_SESSION', [], null, null, false);
        $report = $h->request('GET', 'report')->body;
        $this->assertSame('BOUNDARY_REACHED', $report['report']['status']);
        $this->assertDoesNotMatchRegularExpression('/incapab|deficit|cannot learn|master/i', json_encode($report['report']['independent_evidence']) . json_encode($report['report']['next_evidence_needed']));
    }

    /** FLOW-07: END_SESSION while provider output is pending → lease cancelled, accepted work kept, partial report; late output discarded. */
    public function testFlow07StopWhilePending(): void
    {
        $h = $this->h;
        $h->start('Q07');
        $h->answerCorrect();
        $rev = $h->view['revision'];
        $other = $h->sibling();
        $other->view = $h->view;
        $closed = null;
        $h->mock->onCall = function () use ($other, $rev, &$closed) {
            $closed = $other->event('END_SESSION', [], null, $rev, false);
        };
        $pendingAnswer = $h->correct();
        $late = $h->event('SUBMIT_RESPONSE', ['text' => $pendingAnswer]);
        $this->assertSame(200, $closed->status, 'END_SESSION is usable while the provider call is pending');
        $this->assertSame('CLOSED', $closed->body['status']);
        $this->assertSame(409, $late->status);
        $this->assertSame('OPERATION_CANCELLED', $late->body['error_code']);
        $snap = $h->row()['snapshot'];
        $this->assertSame('SESSION_CLOSED', $snap['mode']);
        $this->assertSame($rev + 1, $snap['revision'], 'only the END_SESSION commit advanced the revision');
        $this->assertCount(1, $h->events('CHECKER_RESULT'), 'accepted work kept; the pending answer was not scored');
        $unscored = $h->events('PENDING_INPUT_UNSCORED');
        $this->assertCount(1, $unscored);
        $this->assertSame($pendingAnswer, $unscored[0]['data']['text']);
        $ops = array_column($h->app->store->db->all('SELECT status FROM operations ORDER BY rowid'), 'status');
        $this->assertContains('CANCELLED', $ops);
        $report = $h->request('GET', 'report')->body['report'];
        $this->assertSame('INTERRUPTED', $report['status']);
        $this->assertTrue((bool) array_filter($report['unresolved_or_untested'], fn ($s) => str_contains($s, 'unscored')));
        // Late output cannot reopen the session.
        $this->assertSame(409, $h->event('SUBMIT_RESPONSE', ['text' => '1'], null, $snap['revision'])->status);
    }
}
