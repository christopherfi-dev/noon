# Architect ruling on reasoning settings (round 3)

Received from the owner on 2026-10-21 (relayed from the master architect).
Recorded verbatim; where it is silent, `CONDUCTOR_ROUND_2.md`,
`CONDUCTOR_ADDENDUM_BETA_v1.md` and the package still govern.

> Architect ruling on reasoning settings (source: api-docs.deepseek.com, Thinking Mode guide and Chat Completions reference):
> 1. Add config keys `provider_thinking` (`disabled` or `enabled`) and `provider_reasoning_effort` (`low`, `high` or `max`). Send `"thinking": {"type": ...}` at the top level of the request body on every call. Send `reasoning_effort` only when thinking is enabled. Never rely on the provider default.
> 2. Send `temperature` only when thinking is disabled. The docs state it has no effect in thinking mode.
> 3. Never store or display `reasoning_content` to the learner or in any public payload, and never send it back to the API. Log only its token count.
> 4. Set `max_tokens` per configuration and record every `finish_reason=length`.
> 5. Run the §4 live run (5 B1 sessions and 3 B3 sessions) three times: with thinking disabled, with thinking enabled at low effort, and with thinking enabled at high effort. Use the same scripted learner inputs each time. For each configuration, report: repairs per operation, HOLDs, host rejections with reasons, empty responses, `finish_reason=length` count, median and slowest call time, and completion and reasoning tokens.
> 6. Do not choose the configuration yourself. Report the three result sets and the transcripts.

Owner's priority for the later choice: first, no output-format failures and no
rule violations; then, response time.

Architect's note on documentation (summary of the owner's message):

- Thinking is on by default at `high` effort.
- `reasoning_effort` accepts `none`, `low`, `high` and `max`. `minimal` is
  treated as `low`; `medium` and `xhigh` are treated as `high`.
- `{"thinking": {"type": "enabled|disabled"}}` goes at the top level of the
  request body.
- Thinking mode ignores `temperature`, `presence_penalty` and
  `frequency_penalty`.
- Without `max_tokens` the output limit is 8K tokens with thinking off and 64K
  with thinking on (128K at `max`).
- `reasoning_content` is returned separately and need not be sent back when no
  tools are used.

Architect's note on §1: `.gitignore` "still contains only one line". See
`BUILD_LOG.md`: the §1 patterns were committed in `0adde50` and pushed before
this ruling arrived.

## Model for the three runs (owner's answer, 2026-10-21)

> Use deepseek-flash for all three runs. Architect ruling: DeepSeek's docs (api-docs.deepseek.com, Your First API Call and the Chat Completions reference) name deepseek-flash as the current model; deepseek-v4-flash is a retired alias served by the same V4.1-Flash model. Do not use deepseek-v4-pro. Record the model name returned in each response.
