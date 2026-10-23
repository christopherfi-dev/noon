<?php
declare(strict_types=1);

namespace AlgebraEngine\Tests\Integration;

use AlgebraEngine\Evidence\CriteriaCalculator;
use AlgebraEngine\Tests\Support\Harness;
use PHPUnit\Framework\TestCase;

/** Acceptance scenarios EVID-01 … EVID-06. */
final class EvidenceTest extends TestCase
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

    /** EVID-01: support requested before a correct answer is committed first; the answer is assisted, excluded, and (round 5.1) resets the run. */
    public function testEvid01HintIndependence(): void
    {
        $h = $this->h;
        $h->start('Q06');
        $h->answerCorrect();
        $this->assertSame(1, $h->view['progress']['independent_successes']);
        $h->event('REQUEST_EXPLANATION');
        $support = $h->events('SUPPORT_GIVEN');
        $this->assertSame('EXPLANATION', $support[0]['data']['kind']);
        $this->assertSame('ASSISTED', $h->view['active_item']['support_status']);
        $h->answerCorrect();
        // Round 5.1 owner rule: an answer given after support breaks the run, exactly as a wrong answer does.
        $this->assertSame(0, $h->view['progress']['independent_successes'], 'an answer after support resets the run');
        $results = $h->events('CHECKER_RESULT');
        $this->assertFalse(end($results)['data']['result']['independent_eligible']);
        $h->answerCorrect();
        $this->assertSame(1, $h->view['progress']['independent_successes']);
    }

    /** EVID-02: a solved instance reused with a new item ID is detected by fingerprint; no independent credit. */
    public function testEvid02Freshness(): void
    {
        $h = $this->h;
        $h->start('Q06');
        $solved = $h->active()['params'];
        $h->answerCorrect();
        $h->mock->faults[] = ['type' => 'ITEM_PATCH', 'patch' => ['parameters' => $solved]];
        $h->answerCorrect();
        // Round 6: the host chooses fresh parameters itself (no repair call within the per-entry limit) and records the proposal.
        $flags = array_values(array_filter($h->events('ITEM_FLAGGED'), fn ($e) => $e['data']['flag'] === 'HOST_REPLACED_PARAMETERS'));
        $this->assertContains('parameters reuse an item instance already presented in this session; choose fresh parameters', $flags[0]['data']['reasons']);
        $this->assertSame($solved, $flags[0]['data']['model_parameters']);
        $fps = array_map(fn ($e) => $e['data']['fingerprint'], $h->events('ITEM_PRESENTED'));
        $this->assertSame(count($fps), count(array_unique($fps)));
        $this->assertNotSame($solved, $h->active()['params']);

        // Ledger level: an item marked as a reused instance never earns credit.
        $events = $h->events();
        foreach ($events as &$e) {
            if ($e['type'] === 'ITEM_PRESENTED') {
                $e['data']['independent_eligible'] = false;
            }
        }
        unset($e);
        $this->assertSame(0, CriteriaCalculator::compute($events)['criteria'][0]['independent_correct_streak']);
    }

    /** EVID-03: three correct numeric answers on a skill that requires conceptual evidence → component criterion only. */
    public function testEvid03NumericOnlySkill(): void
    {
        $h = $this->h;
        $h->start('Q06');
        for ($i = 0; $i < 3; $i++) {
            $h->answerCorrect();
        }
        $this->assertSame('SESSION_REPORT', $h->mode());
        $c = $h->row()['snapshot']['criteria'][0];
        $this->assertSame(['COMPONENT_CRITERION_MET', false], [$c['immediate_status'], $c['whole_skill_requirements_satisfied']]);
        $this->assertFalse($h->view['progress']['whole_skill_claim']);
        $report = $h->request('GET', 'report')->body;
        $this->assertSame('PARTIAL_COMPONENTS (numeric component only)', $report['per_skill'][0]['runtime_coverage']);
        $this->assertTrue((bool) array_filter($report['report']['unresolved_or_untested'], fn ($s) => str_starts_with($s, 'Q06: only the numeric component')));
        // For a three-variant skill, three constructed items alone do not meet the criterion.
        $events = [];
        for ($i = 0; $i < 3; $i++) {
            $events[] = ['event_id' => "p$i", 'type' => 'ITEM_PRESENTED', 'data' => ['item_id' => "i$i", 'item_version' => 1, 'skill_id' => 'Q07', 'role' => 'TARGET', 'variant_id' => 'constructed', 'episode_id' => 'ep', 'independent_eligible' => true]];
            $events[] = ['event_id' => "r$i", 'type' => 'RESPONSE_SUBMITTED', 'data' => ['item_id' => "i$i", 'item_version' => 1, 'response_sha256' => str_repeat('a', 64)]];
            $events[] = ['event_id' => "c$i", 'type' => 'CHECKER_RESULT', 'data' => ['result' => ['checker_result_id' => "k$i", 'item_id' => "i$i", 'item_version' => 1, 'event_id' => "r$i", 'response_sha256' => str_repeat('a', 64), 'verdict' => 'CORRECT', 'independent_eligible' => true]]];
        }
        $computed = CriteriaCalculator::compute($events);
        $this->assertSame(3, $computed['criteria'][0]['independent_correct_streak']);
        $this->assertSame('NOT_MET', $computed['criteria'][0]['immediate_status']);
    }

    /** EVID-04: three varied, fully eligible successes in one session → immediate criterion only; no retention claim. */
    public function testEvid04ImmediateVersusRetention(): void
    {
        $h = $this->h;
        $h->start('E06');
        for ($i = 0; $i < 3; $i++) {
            $h->answerCorrect();
        }
        $report = $h->request('GET', 'report')->body['report'];
        $this->assertCount(1, $report['immediate_criteria']);
        $this->assertStringContainsString('no retention or long-term learning claim', $report['immediate_criteria'][0]['review_scope']);
        $this->assertSame([], $report['retention_evidence']);
        $this->assertStringContainsString('later independent review', $report['next_evidence_needed'][0]);
        $this->assertStringContainsString('a check from this session only, not a long-term learning claim', $report['policy_note']);
    }

    /** EVID-05: invalidating an earlier credited item appends a correction, preserves the original and recomputes claims. */
    public function testEvid05CorrectionCascade(): void
    {
        $h = $this->h;
        $h->start('Q07');
        for ($i = 0; $i < 3; $i++) {
            $h->answerCorrect();
        }
        $this->assertSame('IMMEDIATE_CRITERION_MET', $h->row()['snapshot']['criteria'][0]['immediate_status']);
        $responses = $h->events('RESPONSE_SUBMITTED');
        $before = count($h->events());
        $h->app->engine->invalidate($h->sessionId(), [$responses[1]['event_id']], 'VERIFICATION_CONFLICT: contrast item key disputed on review.');
        $snap = $h->row()['snapshot'];
        $this->assertSame('NOT_MET', $snap['criteria'][0]['immediate_status']);
        $this->assertSame(2, $snap['criteria'][0]['independent_correct_streak']);
        $this->assertCount(1, $h->events('CORRECTION'));
        $this->assertGreaterThan($before, count($h->events()));
        $this->assertCount(3, $h->events('RESPONSE_SUBMITTED'), 'original records preserved');
        $this->assertCount(1, $h->events('CRITERION_MET'), 'the original claim stays in the ledger history');
        $report = $h->request('GET', 'report')->body['report'];
        $this->assertSame([], $report['immediate_criteria']);
        $this->assertTrue((bool) array_filter($report['unresolved_or_untested'], fn ($s) => str_contains($s, 'withdrawn after a correction')));
    }

    /** EVID-06: a report after one Q07 journey labels no untested curriculum skill as passed or failed. */
    public function testEvid06UntestedMap(): void
    {
        $h = $this->h;
        $h->start('Q07');
        $h->submit($h->wrong('DIST_X_ONLY'));
        $h->answerCorrect();
        $h->event('END_SESSION', [], null, null, false);
        $report = $h->request('GET', 'report')->body;
        $this->assertSame(['Q07'], array_column($report['per_skill'], 'skill_id'));
        $this->assertSame(['Q07'], $report['report']['targets_attempted']);
        $this->assertContains('Skills not listed above were not tested in this session and carry no pass or fail label.', $report['report']['unresolved_or_untested']);
        $json = json_encode($report);
        foreach ($h->app->curriculum->ids() as $id) {
            if ($id !== 'Q07') {
                $this->assertStringNotContainsString('"skill_id":"' . $id . '"', $json, "$id must not be labelled");
            }
        }
        $this->assertDoesNotMatchRegularExpression('/\b(failed|passed)\b/i', json_encode($report['report']['independent_evidence']));
        $this->assertStringContainsString('Fresh Q07 work without help', $report['report']['next_evidence_needed'][0]);
    }
}
