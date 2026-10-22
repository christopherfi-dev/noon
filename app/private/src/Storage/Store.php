<?php
declare(strict_types=1);

namespace AlgebraEngine\Storage;

use AlgebraEngine\Support\Ids;
use AlgebraEngine\Support\Json;
use AlgebraEngine\Support\Time;

/** Table access for containers, sessions, the event ledger, items, verdicts, operations and idempotency. */
final class Store
{
    public const ARCHITECTURE_VERSION = '1.2';
    public const CURRICULUM_VERSION = '1.1.0';

    public function __construct(public Database $db)
    {
    }

    // ------------------------------------------------------------- containers

    public function containerByCookie(string $cookie): ?array
    {
        return $this->db->one('SELECT * FROM containers WHERE cookie_hash = ?', [hash('sha256', $cookie)]);
    }

    /** @return array{0:array<string,mixed>,1:string} row and the new cookie value */
    public function createContainer(\DateTimeImmutable $now, int $ttl): array
    {
        $id = Ids::make('ctr');
        $cookie = Ids::secret();
        $this->db->exec(
            'INSERT INTO containers (container_id, cookie_hash, csrf_token, current_session_id, created_at, last_seen_at, expires_at) VALUES (?,?,?,?,?,?,?)',
            [$id, hash('sha256', $cookie), Ids::secret(), null, Time::iso($now), Time::iso($now), Time::iso($now->modify("+$ttl seconds"))],
        );
        return [$this->db->one('SELECT * FROM containers WHERE container_id = ?', [$id]), $cookie];
    }

    public function touchContainer(string $id, \DateTimeImmutable $now, int $ttl): void
    {
        $this->db->exec('UPDATE containers SET last_seen_at = ?, expires_at = ? WHERE container_id = ?', [Time::iso($now), Time::iso($now->modify("+$ttl seconds")), $id]);
    }

    /** Rotate the cookie credential (session replacement). @return string new cookie */
    public function rotateCookie(string $containerId): string
    {
        $cookie = Ids::secret();
        $this->db->exec('UPDATE containers SET cookie_hash = ? WHERE container_id = ?', [hash('sha256', $cookie), $containerId]);
        return $cookie;
    }

    public function setCurrentSession(string $containerId, ?string $sessionId): void
    {
        $this->db->exec('UPDATE containers SET current_session_id = ? WHERE container_id = ?', [$sessionId, $containerId]);
    }

    public function deleteContainer(string $containerId): void
    {
        $this->db->exec('DELETE FROM idempotency WHERE scope_id = ?', [$containerId]);
        $this->db->exec('DELETE FROM containers WHERE container_id = ?', [$containerId]);
    }

    // --------------------------------------------------------------- sessions

    public function insertSession(string $sessionId, string $containerId, array $snapshot, array $engine, \DateTimeImmutable $now, int $idleTtl, int $absoluteTtl): void
    {
        $this->db->exec(
            'INSERT INTO sessions (session_id, container_id, revision, status, snapshot_json, engine_json, pending_operation_id, model_calls, created_at, last_activity_at, idle_expires_at, absolute_expires_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            [$sessionId, $containerId, $snapshot['revision'], $engine['status'], Json::encode($snapshot), Json::encode($engine), null, 0,
                Time::iso($now), Time::iso($now), Time::iso($now->modify("+$idleTtl seconds")), Time::iso($now->modify("+$absoluteTtl seconds"))],
        );
    }

    /** @return array<string,mixed>|null */
    public function session(string $sessionId): ?array
    {
        $row = $this->db->one('SELECT * FROM sessions WHERE session_id = ?', [$sessionId]);
        if ($row === null) {
            return null;
        }
        $row['snapshot'] = Json::decode($row['snapshot_json'], 128);
        $row['engine'] = Json::decode($row['engine_json'], 128);
        $row['revision'] = (int) $row['revision'];
        $row['model_calls'] = (int) $row['model_calls'];
        return $row;
    }

    public function saveSession(string $sessionId, array $snapshot, array $engine, \DateTimeImmutable $now, int $idleTtl): void
    {
        $this->db->exec(
            'UPDATE sessions SET revision = ?, status = ?, snapshot_json = ?, engine_json = ?, pending_operation_id = ?, last_activity_at = ?, idle_expires_at = ? WHERE session_id = ?',
            [$snapshot['revision'], $engine['status'], Json::encode($snapshot), Json::encode($engine), $snapshot['pending_operation_id'],
                Time::iso($now), Time::iso($now->modify("+$idleTtl seconds")), $sessionId],
        );
    }

    public function setPendingOperation(string $sessionId, ?string $operationId): void
    {
        $this->db->exec('UPDATE sessions SET pending_operation_id = ? WHERE session_id = ?', [$operationId, $sessionId]);
    }

    public function addModelCalls(string $sessionId, int $n): void
    {
        $this->db->exec('UPDATE sessions SET model_calls = model_calls + ? WHERE session_id = ?', [$n, $sessionId]);
    }

    /** Forget: delete the session and everything derived from it. */
    public function deleteSession(string $sessionId): void
    {
        foreach (['events', 'items', 'checker_results', 'operations', 'reports', 'model_calls'] as $table) {
            $this->db->exec("DELETE FROM $table WHERE session_id = ?", [$sessionId]);
        }
        $this->db->exec('DELETE FROM idempotency WHERE scope_id = ?', [$sessionId]);
        $this->db->exec('DELETE FROM sessions WHERE session_id = ?', [$sessionId]);
        $this->db->exec('UPDATE containers SET current_session_id = NULL WHERE current_session_id = ?', [$sessionId]);
    }

    /** Bounded opportunistic cleanup of expired sessions and containers. @return int sessions deleted */
    public function cleanupExpired(\DateTimeImmutable $now, int $limit = 20): int
    {
        $iso = Time::iso($now);
        $ids = $this->db->all('SELECT session_id FROM sessions WHERE idle_expires_at < ? OR absolute_expires_at < ? LIMIT ' . (int) $limit, [$iso, $iso]);
        foreach ($ids as $r) {
            $this->db->transaction(fn () => $this->deleteSession($r['session_id']));
        }
        $this->db->exec('DELETE FROM containers WHERE expires_at < ? AND current_session_id IS NULL', [$iso]);
        $this->db->exec('DELETE FROM counters WHERE expires_at < ?', [$iso]);
        return count($ids);
    }

    // ----------------------------------------------------------------- events

    public function appendEvent(string $sessionId, string $type, int $revision, array $data, \DateTimeImmutable $now, ?string $eventId = null): string
    {
        $eventId ??= Ids::make('evt');
        $this->db->exec(
            'INSERT INTO events (event_id, session_id, type, revision, data_json, architecture_version, curriculum_version, created_at) VALUES (?,?,?,?,?,?,?,?)',
            [$eventId, $sessionId, $type, $revision, Json::encode($data), self::ARCHITECTURE_VERSION, self::CURRICULUM_VERSION, Time::iso($now)],
        );
        return $eventId;
    }

    /** @return list<array{event_id:string,type:string,revision:int,data:array,created_at:string}> */
    public function events(string $sessionId): array
    {
        $rows = $this->db->all('SELECT event_id, type, revision, data_json, created_at FROM events WHERE session_id = ? ORDER BY seq', [$sessionId]);
        return array_map(fn ($r) => [
            'event_id' => $r['event_id'],
            'type' => $r['type'],
            'revision' => (int) $r['revision'],
            'data' => Json::decode($r['data_json'], 128),
            'created_at' => $r['created_at'],
        ], $rows);
    }

    // ------------------------------------------------------------------ items

    public function insertItem(string $sessionId, array $record, string $fingerprint, \DateTimeImmutable $now): void
    {
        $this->db->exec(
            'INSERT INTO items (item_id, item_version, session_id, record_json, fingerprint, created_at) VALUES (?,?,?,?,?,?)',
            [$record['item_id'], $record['item_version'], $sessionId, Json::encode($record), $fingerprint, Time::iso($now)],
        );
    }

    public function item(string $sessionId, string $itemId, int $version): ?array
    {
        $row = $this->db->one('SELECT record_json FROM items WHERE session_id = ? AND item_id = ? AND item_version = ?', [$sessionId, $itemId, $version]);
        return $row === null ? null : Json::decode($row['record_json'], 64);
    }

    /** @return list<string> fingerprints already presented in this session */
    public function fingerprints(string $sessionId): array
    {
        return array_column($this->db->all('SELECT fingerprint FROM items WHERE session_id = ?', [$sessionId]), 'fingerprint');
    }

    // -------------------------------------------------------- checker results

    public function insertCheckerResult(string $sessionId, array $result): void
    {
        $this->db->exec(
            'INSERT INTO checker_results (checker_result_id, session_id, item_id, item_version, event_id, response_sha256, result_json, created_at) VALUES (?,?,?,?,?,?,?,?)',
            [$result['checker_result_id'], $sessionId, $result['item_id'], $result['item_version'], $result['event_id'], $result['response_sha256'], Json::encode($result), $result['created_at']],
        );
    }

    /** @return list<array<string,mixed>> */
    public function checkerResults(string $sessionId): array
    {
        return array_map(fn ($r) => Json::decode($r['result_json'], 64), $this->db->all('SELECT result_json FROM checker_results WHERE session_id = ? ORDER BY created_at, rowid', [$sessionId]));
    }

    // ------------------------------------------------------------- operations

    /** @return array{operation_id:string,lease_token:string} */
    public function claimOperation(string $sessionId, int $baseRevision, string $requestHash, \DateTimeImmutable $now, int $leaseSeconds): array
    {
        $op = ['operation_id' => Ids::make('op'), 'lease_token' => Ids::secret()];
        $this->db->exec(
            'INSERT INTO operations (operation_id, session_id, lease_token, base_revision, request_hash, status, created_at, expires_at) VALUES (?,?,?,?,?,?,?,?)',
            [$op['operation_id'], $sessionId, $op['lease_token'], $baseRevision, $requestHash, 'ACTIVE', Time::iso($now), Time::iso($now->modify("+$leaseSeconds seconds"))],
        );
        $this->setPendingOperation($sessionId, $op['operation_id']);
        return $op;
    }

    /** Retain the in-flight learner input so a cancel can report it as pending/unscored. */
    public function setPendingInput(string $operationId, ?array $input): void
    {
        $this->db->exec('UPDATE operations SET pending_input_json = ? WHERE operation_id = ?', [$input === null ? null : Json::encode($input), $operationId]);
    }

    public function operation(string $operationId): ?array
    {
        return $this->db->one('SELECT * FROM operations WHERE operation_id = ?', [$operationId]);
    }

    public function finishOperation(string $operationId, string $status, \DateTimeImmutable $now): void
    {
        $this->db->exec('UPDATE operations SET status = ?, finished_at = ? WHERE operation_id = ?', [$status, Time::iso($now), $operationId]);
    }

    // ------------------------------------------------------------ idempotency

    public function idempotency(string $scope, string $key): ?array
    {
        return $this->db->one('SELECT * FROM idempotency WHERE scope_id = ? AND idem_key = ?', [$scope, $key]);
    }

    public function idempotencyPending(string $scope, string $key, string $hash, \DateTimeImmutable $now): void
    {
        $this->db->exec('INSERT INTO idempotency (scope_id, idem_key, request_hash, status, http_status, response_json, created_at) VALUES (?,?,?,?,?,?,?)',
            [$scope, $key, $hash, 'PENDING', null, null, Time::iso($now)]);
    }

    public function idempotencyComplete(string $scope, string $key, int $status, array $response): void
    {
        $this->db->exec('UPDATE idempotency SET status = ?, http_status = ?, response_json = ? WHERE scope_id = ? AND idem_key = ?',
            ['COMPLETE', $status, Json::encode($response), $scope, $key]);
    }

    public function idempotencyMove(string $fromScope, string $toScope, string $key): void
    {
        $this->db->exec('UPDATE idempotency SET scope_id = ? WHERE scope_id = ? AND idem_key = ?', [$toScope, $fromScope, $key]);
    }

    // ----------------------------------------------------------------- reports

    public function insertReport(string $sessionId, int $revision, array $report, \DateTimeImmutable $now): void
    {
        $this->db->exec('INSERT INTO reports (report_id, session_id, revision, report_json, created_at) VALUES (?,?,?,?,?)',
            [$report['report']['report_id'], $sessionId, $revision, Json::encode($report), Time::iso($now)]);
    }

    public function latestReport(string $sessionId): ?array
    {
        $row = $this->db->one('SELECT report_json FROM reports WHERE session_id = ? ORDER BY rowid DESC LIMIT 1', [$sessionId]);
        return $row === null ? null : Json::decode($row['report_json'], 64);
    }

    // ---------------------------------------------------------------- counters

    /** Increment a windowed counter and return its new value. */
    public function bump(string $bucket, \DateTimeImmutable $expiresAt, int $by = 1): int
    {
        $this->db->exec('INSERT INTO counters (bucket, count, expires_at) VALUES (?, ?, ?) ON CONFLICT(bucket) DO UPDATE SET count = count + excluded.count',
            [$bucket, $by, Time::iso($expiresAt)]);
        return (int) $this->db->scalar('SELECT count FROM counters WHERE bucket = ?', [$bucket]);
    }

    public function counter(string $bucket): int
    {
        return (int) ($this->db->scalar('SELECT count FROM counters WHERE bucket = ?', [$bucket]) ?? 0);
    }

    // ------------------------------------------------------------- call log

    /** @param array{finish_reason?:?string,duration_ms?:?int,prompt_tokens?:?int,completion_tokens?:?int,reasoning_tokens?:?int,transport_detail?:?array} $meta */
    public function logModelCall(?string $sessionId, ?string $operationId, string $kind, string $provider, string $model, string $outcome, \DateTimeImmutable $now, array $meta = []): void
    {
        $this->db->exec('INSERT INTO model_calls (call_id, session_id, operation_id, kind, provider, model, outcome, created_at, finish_reason, prompt_tokens, completion_tokens, reasoning_tokens, duration_ms, transport_detail) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [Ids::make('call'), $sessionId, $operationId, $kind, $provider, $model, $outcome, Time::iso($now),
                $meta['finish_reason'] ?? null, $meta['prompt_tokens'] ?? null, $meta['completion_tokens'] ?? null, $meta['reasoning_tokens'] ?? null, $meta['duration_ms'] ?? null,
                isset($meta['transport_detail']) ? Json::encode($meta['transport_detail']) : null]);
    }

    public function modelCallCount(string $sessionId): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM model_calls WHERE session_id = ?', [$sessionId]);
    }
}
