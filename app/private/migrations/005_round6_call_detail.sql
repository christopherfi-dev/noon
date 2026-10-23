-- Round 6 §3.6: which reasoning setting code chose for each call, and what
-- the call was for (binding, reply, reading, repair), with the learner turn it
-- belongs to so calls per turn can be counted. Adds columns only; existing
-- rows keep NULL. Safe to run once per database (the migration table guards it).
ALTER TABLE model_calls ADD COLUMN thinking TEXT;
ALTER TABLE model_calls ADD COLUMN reasoning_effort TEXT;
ALTER TABLE model_calls ADD COLUMN call_purpose TEXT;
ALTER TABLE model_calls ADD COLUMN turn_id TEXT;
