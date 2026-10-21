-- Algebra Engine beta: private SQLite storage. All times are UTC ISO-8601.
CREATE TABLE IF NOT EXISTS schema_migrations (version INTEGER PRIMARY KEY, applied_at TEXT NOT NULL);

CREATE TABLE IF NOT EXISTS containers (
    container_id TEXT PRIMARY KEY,
    cookie_hash TEXT NOT NULL UNIQUE,
    csrf_token TEXT NOT NULL,
    current_session_id TEXT,
    created_at TEXT NOT NULL,
    last_seen_at TEXT NOT NULL,
    expires_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS sessions (
    session_id TEXT PRIMARY KEY,
    container_id TEXT NOT NULL,
    revision INTEGER NOT NULL,
    status TEXT NOT NULL,
    snapshot_json TEXT NOT NULL,
    engine_json TEXT NOT NULL,
    pending_operation_id TEXT,
    model_calls INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    last_activity_at TEXT NOT NULL,
    idle_expires_at TEXT NOT NULL,
    absolute_expires_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS sessions_container ON sessions(container_id);

CREATE TABLE IF NOT EXISTS events (
    seq INTEGER PRIMARY KEY AUTOINCREMENT,
    event_id TEXT NOT NULL UNIQUE,
    session_id TEXT NOT NULL,
    type TEXT NOT NULL,
    revision INTEGER NOT NULL,
    data_json TEXT NOT NULL,
    architecture_version TEXT NOT NULL,
    curriculum_version TEXT NOT NULL,
    created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS events_session ON events(session_id, seq);

CREATE TABLE IF NOT EXISTS items (
    item_id TEXT NOT NULL,
    item_version INTEGER NOT NULL,
    session_id TEXT NOT NULL,
    record_json TEXT NOT NULL,
    fingerprint TEXT NOT NULL,
    created_at TEXT NOT NULL,
    PRIMARY KEY (item_id, item_version)
);
CREATE INDEX IF NOT EXISTS items_session ON items(session_id);

CREATE TABLE IF NOT EXISTS checker_results (
    checker_result_id TEXT PRIMARY KEY,
    session_id TEXT NOT NULL,
    item_id TEXT NOT NULL,
    item_version INTEGER NOT NULL,
    event_id TEXT NOT NULL,
    response_sha256 TEXT NOT NULL,
    result_json TEXT NOT NULL,
    created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS checker_session ON checker_results(session_id);

CREATE TABLE IF NOT EXISTS operations (
    operation_id TEXT PRIMARY KEY,
    session_id TEXT NOT NULL,
    lease_token TEXT NOT NULL,
    base_revision INTEGER NOT NULL,
    request_hash TEXT NOT NULL,
    status TEXT NOT NULL,
    created_at TEXT NOT NULL,
    expires_at TEXT NOT NULL,
    finished_at TEXT,
    pending_input_json TEXT
);
CREATE INDEX IF NOT EXISTS operations_session ON operations(session_id);

CREATE TABLE IF NOT EXISTS idempotency (
    scope_id TEXT NOT NULL,
    idem_key TEXT NOT NULL,
    request_hash TEXT NOT NULL,
    status TEXT NOT NULL,
    http_status INTEGER,
    response_json TEXT,
    created_at TEXT NOT NULL,
    PRIMARY KEY (scope_id, idem_key)
);

CREATE TABLE IF NOT EXISTS reports (
    report_id TEXT PRIMARY KEY,
    session_id TEXT NOT NULL,
    revision INTEGER NOT NULL,
    report_json TEXT NOT NULL,
    created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS reports_session ON reports(session_id);

CREATE TABLE IF NOT EXISTS counters (
    bucket TEXT PRIMARY KEY,
    count INTEGER NOT NULL,
    expires_at TEXT NOT NULL
);

-- Redacted provider call log: no prompts, no learner text, no keys.
CREATE TABLE IF NOT EXISTS model_calls (
    call_id TEXT PRIMARY KEY,
    session_id TEXT,
    operation_id TEXT,
    kind TEXT NOT NULL,
    provider TEXT NOT NULL,
    model TEXT NOT NULL,
    outcome TEXT NOT NULL,
    created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS model_calls_session ON model_calls(session_id);
