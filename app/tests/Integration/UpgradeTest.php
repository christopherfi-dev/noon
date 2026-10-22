<?php
declare(strict_types=1);

namespace AlgebraEngine\Tests\Integration;

use AlgebraEngine\Storage\Database;
use AlgebraEngine\Support\Json;
use AlgebraEngine\Support\ManualClock;
use AlgebraEngine\Tests\Support\Harness;
use PHPUnit\Framework\TestCase;

/**
 * Conductor round UI v3, U11: this release over a live database of the
 * previous release (commit a1f9e16). The fixture was made with that release
 * (app/tools/make_upgrade_fixture.php): a Q07 session left live in guided
 * repair at E06.
 */
final class UpgradeTest extends TestCase
{
    private const FIXTURE = ALGEBRA_APP_ROOT . '/tests/fixtures/upgrade-a1f9e16';
    private const OLD_TABLES = ['containers', 'sessions', 'events', 'items', 'checker_results', 'operations', 'idempotency', 'reports', 'counters', 'model_calls'];

    private string $db;

    protected function setUp(): void
    {
        $this->db = sys_get_temp_dir() . '/ae_upgrade_' . bin2hex(random_bytes(6)) . '.sqlite';
        copy(self::FIXTURE . '/algebra.sqlite', $this->db);
    }

    protected function tearDown(): void
    {
        foreach (['', '-wal', '-shm'] as $s) {
            @unlink($this->db . $s);
        }
    }

    /** @return array<string,string> sha256 of every row of every table the previous release had */
    private function contents(): array
    {
        $pdo = new \PDO('sqlite:' . $this->db);
        $out = [];
        foreach (self::OLD_TABLES as $t) {
            $rows = $pdo->query("SELECT * FROM $t ORDER BY rowid")->fetchAll(\PDO::FETCH_ASSOC);
            $out[$t] = count($rows) . ':' . hash('sha256', json_encode($rows));
        }
        return $out;
    }

    public function testLiveSessionContinuesAfterTheUpgrade(): void
    {
        $meta = Json::decode((string) file_get_contents(self::FIXTURE . '/fixture.json'));
        $this->assertSame('a1f9e16', $meta['release']);
        $this->assertSame([1, 2, 3], $meta['schema_migrations']);
        $before = $this->contents();

        // Applying the release: the migration runs on first use, and again harmlessly.
        $h = new Harness([], null, null, $this->db, new ManualClock($meta['clock']), 'upgrade');
        new Database($this->db, ALGEBRA_APP_ROOT . '/private/migrations');
        $versions = array_map('intval', array_column($h->app->store->db->all('SELECT version FROM schema_migrations ORDER BY version'), 'version'));
        $this->assertSame([1, 2, 3, 4], $versions, 'applied once');
        $this->assertSame($before, $this->contents(), 'no existing row deleted or rewritten');
        $this->assertSame(0, (int) $h->app->store->db->scalar('SELECT COUNT(*) FROM progress'));

        // The browser that was mid-repair carries on.
        $h->cookies = $meta['cookies'];
        $h->csrf = $meta['csrf'];
        $state = $h->request('GET', 'state');
        $this->assertSame(200, $state->status);
        $h->view = $state->body['session'];
        $this->assertSame([$meta['mode'], $meta['revision']], [$h->view['mode'], $h->view['revision']]);
        $this->assertSame('E06', $h->skill());
        $r = $h->submit($h->correct());
        $this->assertSame(200, $r->status);
        $this->assertSame($meta['revision'] + 1, $h->view['revision']);

        // A code is created at its next answer.
        $row = $h->app->store->progressBySession($h->sessionId());
        $this->assertNotNull($row);
        $this->assertSame('E06', $row['record']['next_skill']);
        $this->assertSame('Q07', $row['record']['route_target']);
        $code = $h->request('GET', 'progress')->body['code'];
        $this->assertMatchesRegularExpression('/^[2-9A-HJKMNP-Z]{4}(-[2-9A-HJKMNP-Z]{4}){2}$/', (string) $code);
        $h->completeGuidedRepair();
        $this->assertSame('INDEPENDENT_CHECK', $h->mode());
    }

    /** Two requests arriving together after the upgrade: the migration is applied once. */
    public function testMigrationIsSafeToRunTwice(): void
    {
        $dir = ALGEBRA_APP_ROOT . '/private/migrations';
        new Database($this->db, $dir);
        new Database($this->db, $dir);
        $pdo = new \PDO('sqlite:' . $this->db);
        $this->assertSame([1, 2, 3, 4], array_map('intval', $pdo->query('SELECT version FROM schema_migrations ORDER BY version')->fetchAll(\PDO::FETCH_COLUMN)));
        // Even replaying the migration's own statements changes nothing.
        $pdo->exec((string) file_get_contents($dir . '/004_progress.sql'));
        $this->assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'progress'")->fetchColumn());
    }
}
