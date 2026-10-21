-- Round 3 ruling 3–4: per-call metadata only. Token counts and the finish
-- reason are logged; reasoning text (reasoning_content) is never stored.
ALTER TABLE model_calls ADD COLUMN finish_reason TEXT;
ALTER TABLE model_calls ADD COLUMN prompt_tokens INTEGER;
ALTER TABLE model_calls ADD COLUMN completion_tokens INTEGER;
ALTER TABLE model_calls ADD COLUMN reasoning_tokens INTEGER;
ALTER TABLE model_calls ADD COLUMN duration_ms INTEGER;
