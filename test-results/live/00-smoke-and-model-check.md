# Round 2 §4.1 — live smoke test and model-list check

Provider: DeepSeek, OpenAI-compatible, base URL https://api.deepseek.com/v1
Configured model: deepseek-v4-flash; reasoning_effort: high (owner's stated choice). Key present: yes (private config outside the repository).
Other settings: shipped defaults (provider_timeout 45 s, lease 90 s, provider_max_tokens 4096); max_model_calls_per_session 80; global_provider_calls_per_day 600.

## 1. Smoke test: `ALGEBRA_CONFIG=<private config> php app/tools/live_smoke.php`

Started 2026-10-21T21:27:26Z, finished 2026-10-21T21:27:57Z.

```
Live smoke test against openai_compatible / deepseek-v4-flash
PASS 1. binding call (text mode) + first Q07 item (JSON mode)  (HTTP 200, READY, 14.7s)
     binding acknowledged: openai_compatible / deepseek-v4-flash (architecture sha256 ef03667dabf7152be63009ec6c1e327a83340b4fff65eef303e3eeb326291390)
     host rejected an output (attempt 0): empty content
     [INFORMATION] Here is a grouped linear equation to work on your own. Take the steps you need on paper, then enter the exact value of x.
     item: Solve 9(x + 8) = 162 for x.
PASS 2. wrong answer -> diagnostic probe item  (HTTP 200, READY, 9.3s)
     [FEEDBACK] That is not correct.
     [INFORMATION] Let's try a fresh grouped equation. You can work it out on paper first, then enter the exact value of x.
     item: Solve 4(x − 3) = 16 for x.
PASS 3. hint (FEEDBACK envelope, disclosure guard active)  (HTTP 200, READY, 6.5s)
     [INFORMATION] This question now counts as practice with help, not as independent work.
     [INFORMATION] Look at the 4 in front of the parentheses: it multiplies the entire group inside them, not only the x. Apply it to every term in that group, then keep the equation balanced as you undo the remaining operations, and enter the exact value of x.
     item: Solve 4(x − 3) = 16 for x.
PASS 4. end session and build the report  (HTTP 200, CLOSED, 0s)
     [INFORMATION] Session ended. Your summary is ready.
Provider calls made: 5
This confirms network and adapter compatibility only. It is not evidence of teaching quality or learning.
LIVE SMOKE PASSED
```

Per provider call (metadata only; no headers, no request bodies):

```
#1 text HTTP 200 finish=stop 1.6s tokens in 8411 (cache hit 0 / miss 8411) out 54 (reasoning 20) content_chars=114
#2 JSON HTTP 200 finish=stop 6.5s tokens in 11937 (cache hit 8320 / miss 3617) out 1364 (reasoning 1364) content_chars=0
#3 JSON HTTP 200 finish=stop 6.5s tokens in 12025 (cache hit 11776 / miss 249) out 1215 (reasoning 803) content_chars=1452
#4 JSON HTTP 200 finish=stop 9.3s tokens in 12407 (cache hit 10368 / miss 2039) out 1915 (reasoning 1268) content_chars=2172
#5 JSON HTTP 200 finish=stop 6.5s tokens in 12040 (cache hit 10368 / miss 1672) out 1278 (reasoning 954) content_chars=1126
```

## 2. Model list (2026-10-21T21:28:04Z)

```
GET https://api.deepseek.com/v1/models -> HTTP 200; listed: deepseek-flash, deepseek-v4-pro
configured model 'deepseek-v4-flash' listed: no
GET https://api.deepseek.com/models -> HTTP 200; listed: deepseek-flash, deepseek-v4-pro
configured model 'deepseek-v4-flash' listed: no
```

## 3. Result

The provider accepted the name deepseek-v4-flash (all 5 calls HTTP 200; smoke test passed), but the name is not in the provider's model list.
Per Round 2 §4.1 the live run stopped here. The 5 B1 and 3 B3 scripted sessions were NOT run. See RUN_LOG.md: the session runner
(app/tools/live_sessions.php) applies the same check and stopped at 2026-10-21T21:32:36Z before any session.

## 4. Live metrics (smoke test only; the only live activity in round 2)

| Metric | Value |
|---|---|
| Binding success on first try | yes (1 text-mode call) |
| Envelope repairs per operation | first Q07 item (PRESENT_ITEM): 1 (empty content); probe item (PRESENT_ITEM): 0; hint (HINT): 0 |
| HOLDs | 0 |
| Disclosure-guard rejections | 0 |
| Other host rejections | 1: empty content (see section 5) |
| Turn latency (3 turns with provider calls: 14.7 s, 9.3 s, 6.5 s) | median 9.3 s, slowest 14.7 s |
| Provider calls | 5 (1 text, 4 JSON) |
| Tokens in | 56820 (cache hit 40832, miss 15988) |
| Tokens out | 5826 (reasoning 4409) |
| Cost | not reported by the provider |

## 5. Every host rejection of model output during the live run

- Smoke step 1, operation PRESENT_ITEM (first Q07 item), attempt 0. Reason: `empty content`.
  Rejected text: (empty). Provider finish_reason was stop, with 1364 completion tokens, all reasoning. The repair attempt was accepted.

This confirms network access and adapter compatibility only. It is not evidence of teaching quality or learning.
