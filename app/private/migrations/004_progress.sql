-- Round UI v3 (U10, U11): progress codes. A record holds skill results only:
-- per skill a status, a date and observed mistake-pattern codes, plus the
-- route target and the next skill. Only the sha256 of a code is stored.
-- This migration only adds a table; it never changes existing rows, and every
-- statement is safe to run again.
CREATE TABLE IF NOT EXISTS progress (
    progress_id TEXT PRIMARY KEY,
    code_sha256 TEXT NOT NULL UNIQUE,
    record_json TEXT NOT NULL,
    live_session_id TEXT,
    code_shown INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    last_used_at TEXT NOT NULL,
    expires_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS progress_live_session ON progress(live_session_id);
CREATE INDEX IF NOT EXISTS progress_expires ON progress(expires_at);
