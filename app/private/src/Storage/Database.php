<?php
declare(strict_types=1);

namespace AlgebraEngine\Storage;

/**
 * PDO SQLite wrapper. Fails clearly when PDO SQLite is unavailable; there is
 * no fallback to text-file storage.
 */
final class Database
{
    private \PDO $pdo;
    private int $depth = 0;

    public function __construct(string $path, private readonly string $migrationsDir)
    {
        if (!extension_loaded('pdo_sqlite')) {
            throw new StorageUnavailable('PDO_SQLITE_MISSING');
        }
        if ($path !== ':memory:') {
            $dir = dirname($path);
            if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
                throw new StorageUnavailable('STORAGE_DIRECTORY_UNWRITABLE');
            }
        }
        try {
            $this->pdo = new \PDO('sqlite:' . $path, null, null, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);
            $this->pdo->exec('PRAGMA busy_timeout = 5000');
            if ($path !== ':memory:') {
                $this->pdo->exec('PRAGMA journal_mode = WAL');
            }
            $this->pdo->exec('PRAGMA foreign_keys = ON');
        } catch (\PDOException $e) {
            throw new StorageUnavailable('STORAGE_OPEN_FAILED');
        }
        $this->migrate();
    }

    private function migrate(): void
    {
        $files = glob($this->migrationsDir . '/*.sql') ?: [];
        sort($files);
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version INTEGER PRIMARY KEY, applied_at TEXT NOT NULL)');
        $applied = array_map('intval', $this->pdo->query('SELECT version FROM schema_migrations')->fetchAll(\PDO::FETCH_COLUMN));
        foreach ($files as $file) {
            $version = (int) basename($file);
            if (in_array($version, $applied, true)) {
                continue;
            }
            $this->pdo->beginTransaction();
            $this->pdo->exec((string) file_get_contents($file));
            $stmt = $this->pdo->prepare('INSERT INTO schema_migrations (version, applied_at) VALUES (?, ?)');
            $stmt->execute([$version, gmdate('Y-m-d\TH:i:s\Z')]);
            $this->pdo->commit();
        }
    }

    /**
     * Run $fn inside a short write transaction (BEGIN IMMEDIATE). Never call
     * the provider from inside this callback.
     *
     * @template T
     * @param callable():T $fn
     * @return T
     */
    public function transaction(callable $fn): mixed
    {
        if ($this->depth > 0) {
            return $fn();
        }
        $this->pdo->exec('BEGIN IMMEDIATE');
        $this->depth++;
        try {
            $result = $fn();
            $this->depth--;
            $this->pdo->exec('COMMIT');
            return $result;
        } catch (\Throwable $e) {
            $this->depth--;
            $this->pdo->exec('ROLLBACK');
            throw $e;
        }
    }

    public function inTransaction(): bool
    {
        return $this->depth > 0;
    }

    /** @param list<mixed> $params */
    public function exec(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /** @param list<mixed> $params @return array<string,mixed>|null */
    public function one(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** @param list<mixed> $params @return list<array<string,mixed>> */
    public function all(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** @param list<mixed> $params */
    public function scalar(string $sql, array $params = []): mixed
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $v = $stmt->fetchColumn();
        return $v === false ? null : $v;
    }
}
