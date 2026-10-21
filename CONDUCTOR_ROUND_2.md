# Conductor Round 2 — Beta Fixes and Live Run

Place this file in the repository root and read it before acting. It answers
`BETA_STOP_REPORT.md` §5 and sets the next stop. Where it is silent,
`CONDUCTOR_ADDENDUM_BETA_v1.md` and the package still govern. Continue
`BUILD_LOG.md` with UTC times; record round 2 start and end as a separate
interval from the beta interval.

## 1. Key handling (do first)

The repository `christopherfi-dev/noon` is public.

1. Add to `.gitignore`: `config.php`, `**/config.php`, `*.env`, `.env*`,
   `config.local.php`. Keep `app/private/config.example.php` tracked
   (add a `!` exception if a pattern catches it).
2. Add a test that fails if any tracked file, or the upload ZIP, contains a
   string matching `sk-[A-Za-z0-9]{20,}` or the literal value of the
   configured key.
3. The provider key lives only in a config file outside the repository,
   passed with `ALGEBRA_CONFIG`. Never print it, log it, commit it, or
   include it in `test-results/` or `BUILD_LOG.md`. Record only "key
   present: yes/no".
4. If at any point the key appears in a commit, stop, report it, and tell the
   owner to revoke the key. Do not try to rewrite history yourself.

## 2. Rulings on report §5

| # | Ruling |
|---|---|
| 1 | Accepted. Runtime contract copies pin "1.2"; package contracts stay unchanged. |
| 2 | Fix. Update the v1.2 title and status header to read v1.2. The addendum's "only these changes" was the architect's omission. |
| 3 | Fix. In both v1.2 copies (root and `app/private/resources/`), delete "Use concise explanations when they bridge an actual conceptual gap." (line 568). At line 723, remove "a concise explanation, " from the list of permitted learner-facing content. Add both to the v1.2 CHANGELOG entry. Re-record the architecture hash. |
| 4 | Accepted. Extend B9 to also fail if the string `examples/model-question.json` appears in any assembled prompt. |
| 5 | Accepted. |
| 6 | Change. A provider timeout or budget stop is not a verification failure. Add `PROVIDER_UNAVAILABLE` to the runtime copy of the issue enum and use it for provider timeouts, HTTP errors and budget stops. Keep `INVALID_ITEM` and `CONTRACT_CONFLICT` as reported. Learner text is unchanged. Update tests. |
| 7 | Accepted. |
| 8 | Change. When the learner is stuck at the smallest guided question, go directly to PREREQUISITE_CHECK on the declared prerequisite of the current repair skill that the stuck step exercises. Do not route through an independent check and a probe first. Tag every guided step with the skill it exercises. Add the edge GUIDED_REPAIR → PREREQUISITE_CHECK to the runtime copy of the workflow policy, restricted to this trigger. If the stuck step's skill is not a declared prerequisite, or is outside the beta, record the boundary as in B4. Add test B10 for both cases. |
| 9–15 | Accepted as implemented. |

## 3. Skill list default

The UI currently shows all 205 skills. Default view: the 10 beta skills.
Keep the full map behind a "Show full map" control with runtime status
labels unchanged.

## 4. Live run

Precondition: §1 complete, all tests green.

1. **Smoke test.** Run `tools/live_smoke.php`. Call the provider's model-list
   endpoint and record whether the configured model name is listed. If it is
   not, stop and report the listed names.
2. **Scripted live sessions.** Using the real provider, run 5 sessions of the
   B1 path and 3 of the B3 path, with learner responses supplied by script.
   Store each session's full learner-visible transcript in
   `test-results/live/` (learner text, tutor text, mode, verdict per turn).
   Strip nothing; the key never appears in transcripts.
3. **Record per session and in total:** binding success on first try;
   envelope repairs per operation; HOLDs; disclosure-guard rejections and
   other host rejections, each with the reason; turn latency (median and
   slowest); tokens in and out; cost if the provider reports it.
4. **Do not tune prompts or guards during the live run.** Record results as
   they occur. Any proposed change goes in the report for the architect.

## 5. Gate and report

Round 2 is done when every test passes (B9 extended, B10 added, key-leak
test added), the live run in §4 has completed or stopped with a recorded
reason, and nothing has been deployed.

Report in the §9 order of the beta addendum, plus:
- the live metrics table;
- the 8 transcripts (attach, do not summarise);
- every host rejection of model output during the live run, with the
  rejected text and the reason.
