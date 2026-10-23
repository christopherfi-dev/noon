-- Round 7 §10: prompt tokens the provider served from its cache (DeepSeek
-- usage.prompt_cache_hit_tokens, or the OpenAI-style
-- usage.prompt_tokens_details.cached_tokens), so the cost of the live runs can
-- be computed from what was billed. Adds a column only; existing rows keep NULL.
ALTER TABLE model_calls ADD COLUMN cached_prompt_tokens INTEGER;
