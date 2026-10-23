<?php
declare(strict_types=1);

namespace AlgebraEngine\Tests\Integration;

use AlgebraEngine\Config;
use AlgebraEngine\Progress\ProgressCode;
use AlgebraEngine\Support\Json;
use AlgebraEngine\Tests\Support\Harness;
use PHPUnit\Framework\TestCase;

/**
 * Conductor round UI v3, U10, kept in round 6 (§8): progress codes carry
 * gradebook statuses and the AI's stated next focus into a later session; on
 * resume code writes "Welcome back" and the AI continues from the record.
 */
final class ProgressCodeTest extends TestCase
{
    private const NOT_FOUND = "That code wasn't found. Check it, or start fresh.";

    /** @var list<Harness> */
    private array $harnesses = [];

    private function harness(?Harness $sameDb = null): Harness
    {
        $h = $sameDb === null ? new Harness() : new Harness([], null, null, $sameDb->dbPath, $sameDb->clock, 'device-' . count($this->harnesses));
        $this->harnesses[] = $h;
        return $h;
    }

    private function row(Harness $h): ?array
    {
        $row = $h->app->store->progressBySession($h->sessionId());
        return $row;
    }

    private function code(Harness $h): string
    {
        $r = $h->request('GET', 'progress');
        $this->assertSame(200, $r->status);
        $this->assertTrue($r->body['linked']);
        $this->assertMatchesRegularExpression('/^[2-9A-HJKMNP-Z]{4}-[2-9A-HJKMNP-Z]{4}-[2-9A-HJKMNP-Z]{4}$/', (string) $r->body['code']);
        return $r->body['code'];
    }

    /** Q07, then a distribution-to-x-only answer: the tutor steps back to E06 and poses practice there. */
    private function intoRepair(Harness $h): void
    {
        $h->start('Q07');
        $p = $h->open()['params'];
        $h->send((string) \AlgebraEngine\Math\Rational::of($p['c'] - $p['b'], $p['a']));
        $this->assertSame('E06', $h->view['focus']['skill_id']);
        $this->assertSame('practice', $h->view['open_problem']['purpose']);
    }

    /** Answer correctly until the tutor wraps up or $skill's goal is met. */
    private function untilGoal(Harness $h, string $skill): void
    {
        for ($n = 0; $n < 30 && !$h->goal($skill)['met'] && $h->view['open_problem'] !== null; $n++) {
            $this->assertSame(200, $h->answerCorrectly()->status);
        }
        $this->assertTrue($h->goal($skill)['met'], $skill . ' goal');
    }

    private function lookup(Harness $h, string $code)
    {
        if ($h->csrf === null) {
            $h->bootstrap();
        }
        return $h->request('POST', 'progress_lookup', ['code' => $code]);
    }

    private function startWith(Harness $h, string $code, ?string $target = null)
    {
        if ($h->csrf === null) {
            $h->bootstrap();
        }
        return $h->request('POST', 'progress_start', ['code' => $code, 'idempotency_key' => Harness::key(), 'target_skill_id' => $target]);
    }

    /** Created at the first answer, shown during the session, updated in the same transaction as every answer. */
    public function testCreatedAtFirstAnswerAndUpdatedInTheSameTransaction(): void
    {
        $h = $this->harness();
        $h->start('Q07');
        $this->assertNull($this->row($h), 'no record before an answer');
        $this->assertSame(['linked' => false, 'code' => null], $h->request('GET', 'progress')->body);

        $p = $h->open()['params'];
        $r = $h->send((string) \AlgebraEngine\Math\Rational::of($p['c'] - $p['b'], $p['a']));
        $this->assertTrue((bool) array_filter($r->cookies, fn ($c) => str_starts_with($c, 'ae_pc=')), 'the code reaches the browser with the first answer');
        $row = $this->row($h);
        $this->assertNotNull($row, 'created at the first answer');
        $this->assertSame(['route_target', 'next_focus', 'next_skill', 'skills'], array_keys($row['record']));
        $this->assertSame('Q07', $row['record']['route_target']);
        $this->assertSame('E06', $row['record']['next_focus'], 'the AI\'s stated next focus');
        $this->assertSame('E06', $row['record']['next_skill']);
        $this->assertSame(['status' => 'NEEDED_HELP', 'date' => '2026-10-21', 'patterns' => ['FIRST_TERM_ONLY']], $row['record']['skills']['Q07']);
        $code = $this->code($h);
        $this->assertSame($row['code_sha256'], ProgressCode::hash(str_replace('-', '', $code)));

        // Updated every turn.
        $h->answerCorrectly();
        $record = $this->row($h)['record'];
        $this->assertArrayHasKey('E06', $record['skills']);

        // Same transaction: a commit that fails after the record was written leaves the record as it was.
        $before = $this->row($h);
        $revision = $h->view['revision'];
        $h->app->store->db->exec("CREATE TRIGGER ui_v3_fail BEFORE INSERT ON events WHEN NEW.type = 'STATE_COMMITTED' BEGIN SELECT RAISE(ABORT, 'test'); END");
        try {
            $h->answerCorrectly();
            $this->fail('the commit should have failed');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('test', $e->getMessage());
        }
        $h->app->store->db->exec('DROP TRIGGER ui_v3_fail');
        $this->assertSame($before['record_json'], $this->row($h)['record_json'], 'record rolled back with the answer');
        $this->assertSame($revision, $h->app->store->session($h->sessionId())['revision']);
        // The code is never stored, only its sha256.
        $this->assertSame([], self::plainCodeInDatabase($h, $code));
    }

    /** @return list<string> files of the database that contain the code in any written form */
    private static function plainCodeInDatabase(Harness $h, string $code): array
    {
        $h->app->store->db->exec('PRAGMA wal_checkpoint(FULL)');
        $found = [];
        foreach (['', '-wal'] as $suffix) {
            $bytes = (string) @file_get_contents($h->dbPath . $suffix);
            foreach ([$code, str_replace('-', '', $code), strtolower($code), strtolower(str_replace('-', '', $code))] as $form) {
                if ($bytes !== '' && str_contains($bytes, $form)) {
                    $found[] = 'db' . $suffix . ': ' . $form;
                }
            }
        }
        return $found;
    }

    /** Tab closed mid-repair: once that session has expired, the code starts a new session at the AI's stated next focus; the AI returns to the route target itself. */
    public function testClosedMidRepairResumesAtTheNextFocusAndTheTutorReturns(): void
    {
        $a = $this->harness();
        $this->intoRepair($a);
        $code = $this->code($a);
        $a->clock->advance(25 * 3600); // the tab was closed; the session expired after a day idle

        $b = $this->harness($a);
        $look = $this->lookup($b, strtolower(str_replace('-', ' ', $code)));
        $this->assertSame(200, $look->status);
        $this->assertFalse($look->body['live']);
        $this->assertFalse($look->body['all_met']);
        $this->assertSame('E06', $look->body['next_skill']['skill_id']);
        $this->assertSame('Q07', $look->body['route_target']['skill_id']);
        $this->assertSame(['did_well', 'trouble', 'worked_on', 'next_time', 'headings', 'empty'], array_keys($look->body['summary']));
        $this->assertSame('Continue with “Apply the distributive property with positive factors”.', $look->body['summary']['next_time'][0], '"Welcome back" is written by code');

        $r = $this->startWith($b, $code);
        $this->assertSame(200, $r->status);
        $this->assertSame('Q07', $b->view['topic']['skill_id']);
        $this->assertSame('E06', $b->view['focus']['skill_id'], 'resumed at the next focus');
        $ctx = TutorLoopTest::context($b);
        $this->assertSame('NEEDED_HELP', $ctx['EARLIER_SESSIONS']['Q07']['status'], 'the AI continues from the record');
        $this->assertSame($b->sessionId(), $b->app->store->progressByHash(ProgressCode::hash(str_replace('-', '', $code)), $b->clock->now())['live_session_id']);
        $this->assertSame($code, $this->code($b), 'the same code on the new device');

        $this->untilGoal($b, 'E06');
        $this->assertSame('Q07', $b->view['focus']['skill_id'], 'the tutor returned to the route target');
        $this->assertSame('Q07', $this->row($b)['record']['next_skill']);

        // Earlier results are labelled with their date and never counted as this session's goals.
        $report = $b->request('GET', 'report')->body;
        $this->assertSame(['E06'], array_column($report['report']['immediate_criteria'], 'skill_id'));
        $earlier = $report['summary']['learner']['earlier'];
        $this->assertContains('Solve equations containing parentheses: needed help on 21 October 2026.', $earlier);
        $this->assertSame($code, $report['progress_code']);
    }

    /** A live session continues exactly; entering the code on a second device takes it over. */
    public function testLiveSessionContinuesExactlyAndTakeOver(): void
    {
        $a = $this->harness();
        $this->intoRepair($a);
        $code = $this->code($a);
        $before = $a->row();
        $active = $a->view['open_problem'];

        // The same browser entering its own code just continues.
        $same = $this->startWith($a, $code);
        $this->assertSame(200, $same->status);
        $this->assertSame($before['revision'], $same->body['revision']);

        $b = $this->harness($a);
        $look = $this->lookup($b, $code);
        $this->assertTrue($look->body['live']);
        $r = $this->startWith($b, $code);
        $this->assertSame(200, $r->status);
        $this->assertSame($a->sessionId(), $b->sessionId(), 'the same session');
        $this->assertSame($active, $b->view['open_problem'], 'exactly where it stopped');
        $this->assertSame($before['revision'] + 1, $b->view['revision'], 'the take-over moves the revision on');
        $this->assertSame(1, count($b->eventsOf('SESSION_CONTINUED_ELSEWHERE')));

        // The earlier device can no longer answer.
        $stale = $a->send('5');
        $this->assertSame(409, $stale->status);
        $this->assertSame(['CONTINUED_ELSEWHERE', 'This progress continued on another device.'], [$stale->body['error_code'], $stale->body['message']]);
        $state = $a->request('GET', 'state');
        $this->assertSame([null, true], [$state->body['session'], $state->body['continued_elsewhere']]);
        $this->assertTrue($a->request('GET', 'bootstrap')->body['continued_elsewhere']);
        $this->assertSame(409, $a->request('GET', 'report')->status);

        // The new device carries on.
        $this->assertSame(200, $b->answerCorrectly()->status);
        $this->assertSame($code, $this->code($b));
    }

    /** A take-over waits for a request that is still in flight, so no update is lost. */
    public function testTakeOverWaitsForAnAnswerInFlight(): void
    {
        $a = $this->harness();
        $this->intoRepair($a);
        $code = $this->code($a);
        $a->app->store->claimOperation($a->sessionId(), $a->view['revision'], 'in-flight', $a->clock->now(), 150);
        $b = $this->harness($a);
        $b->bootstrap();
        $r = $this->startWith($b, $code);
        $this->assertSame(409, $r->status);
        $this->assertSame('OPERATION_PENDING', $r->body['error_code']);
        $this->assertNotSame($b->app->store->session($a->sessionId())['container_id'], $b->app->store->containerByCookie($b->cookies['ae_sid'])['container_id']);
    }

    /** Every goal on the route met: say so, and offer the beta skills. */
    public function testAllGoalsMet(): void
    {
        $a = $this->harness();
        $this->intoRepair($a);
        $this->untilGoal($a, 'E06');
        $this->untilGoal($a, 'Q07');
        $this->assertTrue($a->view['wrap_up']);
        $code = $this->code($a);
        $this->assertSame(200, $a->event('END_SESSION', [], null, null, false)->status, 'the student finishes after the wrap-up');
        $this->assertNull($this->row($a)['record']['next_skill']);

        $b = $this->harness($a);
        $look = $this->lookup($b, $code);
        $this->assertSame([false, true, null], [$look->body['live'], $look->body['all_met'], $look->body['next_skill']]);
        $none = $this->startWith($b, $code);
        $this->assertSame([409, 'ALL_GOALS_MET'], [$none->status, $none->body['error_code']]);
        $this->assertSame(200, $this->startWith($b, $code, 'N05')->status);
        $this->assertSame('N05', $b->view['topic']['skill_id']);
        $b->answerCorrectly();
        $this->assertSame('N05', $this->row($b)['record']['route_target'], 'a newly chosen skill becomes the route target');
        $this->assertSame('GOAL_MET', $this->row($b)['record']['skills']['E06']['status'], 'earlier results stay in the record');
    }

    /** An earlier goal is listed with its date and never counts as met in the new session. */
    public function testEarlierGoalsLabelledAndNotCounted(): void
    {
        $a = $this->harness();
        $this->intoRepair($a);
        $this->untilGoal($a, 'E06');
        $code = $this->code($a);
        $this->assertSame('GOAL_MET', $this->row($a)['record']['skills']['E06']['status']);
        $this->assertSame('Q07', $this->row($a)['record']['next_skill']);
        $a->clock->advance(3 * 86400);

        $b = $this->harness($a);
        $this->assertSame(200, $this->startWith($b, $code)->status);
        $this->assertSame('Q07', $b->view['focus']['skill_id']);
        $this->assertSame([], $b->row()['snapshot']['goals'], 'nothing carried into this session\'s goals');
        $this->assertFalse($b->goal('E06')['met']);
        $report = $b->request('GET', 'report')->body;
        $this->assertSame([], $report['report']['immediate_criteria']);
        $this->assertSame([], $report['summary']['learner']['did_well']);
        $this->assertSame('No goals were met in this session yet.', $report['summary']['learner']['empty']['did_well']);
        $this->assertContains('Apply the distributive property with positive factors: goal met on 21 October 2026.', $report['summary']['learner']['earlier']);
        $teacherEarlier = array_values(array_filter($report['summary']['teacher']['sections'], fn ($s) => $s['heading'] === 'From earlier sessions'))[0]['lines'];
        $this->assertContains('Apply the distributive property with positive factors (E06): goal met on 21 October 2026.', $teacherEarlier);
    }

    /** Input in any case, with or without spaces or dashes; one generic error that never reveals whether a code exists. */
    public function testNormalisationAndGenericError(): void
    {
        $a = $this->harness();
        $a->start('Q07');
        $a->answerCorrectly();
        $code = $this->code($a);
        $plain = str_replace('-', '', $code);
        $b = $this->harness($a);
        foreach ([$code, strtolower($code), $plain, ' ' . implode(' ', str_split($plain, 4)) . ' ', implode('-', str_split(strtolower($plain), 3))] as $typed) {
            $this->assertSame(200, $this->lookup($b, $typed)->status, $typed);
        }
        $unknown = ProgressCode::generate();
        while ($unknown === $plain) {
            $unknown = ProgressCode::generate();
        }
        $bodies = [];
        foreach ([$unknown, 'K7QM-4XRT-9WP', 'O0I1L-ABCD-EFGH', '', str_repeat('A', 80)] as $typed) {
            $r = $this->lookup($b, $typed);
            $bodies[] = [$r->status, $r->body];
        }
        $this->assertSame(array_fill(0, 5, [404, ['error_code' => 'CODE_NOT_FOUND', 'message' => self::NOT_FOUND]]), $bodies);
    }

    /** At most 10 failed lookups per hour per browser session. */
    public function testRateLimit(): void
    {
        $a = $this->harness();
        $a->start('Q07');
        $a->answerCorrectly();
        $code = $this->code($a);
        $b = $this->harness($a);
        for ($i = 0; $i < 10; $i++) {
            $this->assertSame(404, $this->lookup($b, 'ZZZZ-ZZZZ-ZZZ' . ($i % 2 === 0 ? 'Y' : 'X'))->status);
        }
        $blocked = $this->lookup($b, $code);
        $this->assertSame(429, $blocked->status, 'even a right code waits once the limit is reached');
        $this->assertSame(429, $this->startWith($b, $code)->status);
        $b->clock->advance(3600);
        $this->assertSame(200, $this->lookup($b, $code)->status, 'the next hour');
    }

    /** Records expire a set number of days after their last use; the existing cleanup deletes them. */
    public function testExpiry(): void
    {
        $a = $this->harness();
        $a->start('Q07');
        $a->answerCorrectly();
        $code = $this->code($a);
        $a->clock->advance(89 * 86400); // each visit is a new browser session
        $this->assertSame(200, $this->lookup($this->harness($a), $code)->status, 'still kept, and this use restarts the clock');
        $a->clock->advance(89 * 86400);
        $this->assertSame(200, $this->lookup($this->harness($a), $code)->status);
        $a->clock->advance(91 * 86400);
        $r = $this->lookup($this->harness($a), $code);
        $this->assertSame([404, self::NOT_FOUND], [$r->status, $r->body['message']]);
        $a->app->store->cleanupExpired($a->clock->now());
        $this->assertSame(0, (int) $a->app->store->db->scalar('SELECT COUNT(*) FROM progress'));
    }

    /** "Forget this session" also deletes the record linked to it, and says so. */
    public function testForgetDeletesTheRecord(): void
    {
        $a = $this->harness();
        $a->start('Q07');
        $a->answerCorrectly();
        $code = $this->code($a);
        $r = $a->request('POST', 'forget', ['idempotency_key' => Harness::key()]);
        $this->assertSame(200, $r->status);
        $this->assertSame(1, $r->body['progress_codes_deleted']);
        $this->assertSame('This session, its summary and its progress code were deleted.', $r->body['message']);
        $this->assertSame(0, (int) $a->app->store->db->scalar('SELECT COUNT(*) FROM progress'));
        $this->assertSame(404, $this->lookup($this->harness($a), $code)->status);
    }

    /** "Start over" begins a fresh session with no code and leaves the record untouched. */
    public function testStartOverLeavesTheRecordUntouched(): void
    {
        $a = $this->harness();
        $this->intoRepair($a);
        $code = $this->code($a);
        $hash = ProgressCode::hash(str_replace('-', '', $code));
        $before = $a->app->store->db->one('SELECT * FROM progress WHERE code_sha256 = ?', [$hash]);
        $this->assertSame(200, $a->start('Q07', true)->status);
        $this->assertNull($this->row($a), 'the fresh session has no code yet');
        $this->assertSame(['linked' => false, 'code' => null], $a->request('GET', 'progress')->body);
        $this->assertSame($before, $a->app->store->db->one('SELECT * FROM progress WHERE code_sha256 = ?', [$hash]));
        $a->answerCorrectly();
        $this->assertNotSame($code, $this->code($a), 'its first answer creates its own code');
        $this->assertSame($before, $a->app->store->db->one('SELECT * FROM progress WHERE code_sha256 = ?', [$hash]));
    }

    /** A code whose first response was lost is replaced once; a code already shown is never replaced. */
    public function testLostCodeIsReplacedOnlyBeforeItWasShown(): void
    {
        $a = $this->harness();
        $a->start('Q07');
        $a->answerCorrectly();
        unset($a->cookies['ae_pc']); // the response that carried the code never arrived
        $first = $this->code($a);
        unset($a->cookies['ae_pc']);
        $this->assertSame(['linked' => true, 'code' => null], $a->request('GET', 'progress')->body, 'a shown code is never replaced');
        $this->assertSame(200, $this->lookup($this->harness($a), $first)->status);
    }

    /** An existing config.php without the new key gets the default. */
    public function testRetentionDefaultWhenConfigLacksTheKey(): void
    {
        $old = ['provider' => 'mock', 'provider_base_url' => '', 'provider_model' => '', 'provider_api_key' => '', 'max_model_calls_per_session' => 150, 'cookie_secure' => 'auto'];
        $this->assertSame(90, Config::fromArray($old, ALGEBRA_APP_ROOT . '/private')->int('progress_retention_days'));
        $this->assertSame(30, Config::fromArray($old + ['progress_retention_days' => 30], ALGEBRA_APP_ROOT . '/private')->int('progress_retention_days'));
        $this->assertStringContainsString('for up to 90 days after last use', (new Harness())->bootstrap()->body['notices']['progress']);
    }

    /** The code format: 12 characters, no 0, O, 1, I or L, shown in groups of four. */
    public function testCodeFormat(): void
    {
        $seen = [];
        for ($i = 0; $i < 2000; $i++) {
            $c = ProgressCode::generate();
            $this->assertMatchesRegularExpression('/^[2-9A-HJKMNP-Z]{12}$/', $c);
            $seen[$c] = true;
        }
        $this->assertCount(2000, $seen);
        $this->assertSame(31, strlen(ProgressCode::ALPHABET));
        $this->assertSame('K7QM-4XRT-9WPC', ProgressCode::format('K7QM4XRT9WPC'));
        $this->assertSame('K7QM4XRT9WPC', ProgressCode::normalize(" k7qm-4xrt 9wpc\t"));
        $this->assertNull(ProgressCode::normalize('K7QM-4XRT-9WP0'));
        $this->assertSame(64, strlen(Json::decode(json_encode(['h' => ProgressCode::hash('K7QM4XRT9WPC')]))['h']));
    }
}
