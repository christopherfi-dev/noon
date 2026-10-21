# 03 — Public API, model protocol and projections

## Endpoints (rewrite-free shared hosting)

Use `/api.php?action=...`; method and CSRF rules still apply. Local development
serves app/public as the document root. Session ownership comes from an opaque
HttpOnly cookie, never a user-entered session_id. Read endpoints return no keys.

| Action | Method | Request / behavior |
|---|---|---|
| bootstrap | GET | Public curriculum titles/capabilities; session view if owned; CSRF token |
| start | POST | entry_route and optional target/task; creates or explicitly replaces current session |
| event | POST | contracts/public-event.schema.json; idempotency and expected revision required |
| state | GET | Current owned session, pending status and safe active item |
| create_task | POST | Public target/title/instructions/duration; returns signed task token/link |
| task | GET | Validate signed token, return public task settings only |
| report | GET | Owned current-session report projection / downloadable JSON |
| forget | POST | Delete current session/evidence and expire its cookie; no model call |

Create task is not an admin endpoint. Use HMAC server signing, a version and
seven-day expiry to protect link integrity; length-limit all fields and verify
signature before accepting. Use signed payload (not a URL with filesystem path).
Allowlisted settings only; no success counts, role, checker policy, curriculum
override, architecture text or report destination. Token contents are public.
Do not put session credentials, learner work or names in the link. Future real
teacher authentication is a separate feature and changes this trust model.

Public event actions: SELECT_TARGET, SUGGEST_TARGET, CONFIRM_TARGET,
START_ORIENTATION, ACCEPT_RECOMMENDATION, SUBMIT_RESPONSE, REQUEST_HINT,
REQUEST_EXPLANATION, CLARIFY_RESPONSE, SKIP_ITEM, NOT_LEARNED, CHANGE_TARGET,
END_SESSION, CONTINUE_SESSION, RESUME_SESSION. Each action's required/allowed
payload is specified in contracts/public-actions.json. Reject unused keys and
incompatible item IDs; do not silently ignore attacker-supplied scoring fields.

## Public response

Use contracts/public-view.schema.json. The session view contains current mode,
revision, safe target title, safe item prompt/input spec, messages, menu and
progress summary, pending status and next permitted actions. Client never sees
the model envelope, answer_spec, rubric keys, hidden hypotheses, raw provider
history, checker derivation, lease token or private event log. A scalar answer
hash alone is not safe publication: small answer spaces are brute-forceable.

Return 400 invalid input, 403 CSRF/ownership, 404 unknown action/task, 409 stale
revision/idempotency or item conflict, 413 oversized body, 422 unsupported task
or syntax with useful next action, 429 rate/cost budget, 503 provider/storage
unavailable. Use stable error_code and user-readable text, no trace or secret.
Status 202 means accepted/in-flight, not a completed learner verdict.

## Binding and provider adapter

Initial architecture-only call uses non-streamed text without JSON mode.
Accept the exact architecture-1.1 handshake; if absent, make one bounded
activation repair request, then hold. A handshake acknowledges instructions;
it does not attest that the provider or app enforces them. Record provider,
model, architecture content hash and accepted activation. Subsequent calls
include the binding exchange plus freshly assembled context and require JSON.
Mock fixtures must exercise both activation and later envelope handling.

Server configuration supplies base URL, model, key and provider feature flags.
Default development provider is mock. The current official DeepSeek docs were
checked on 2026-10-21; see RESEARCH_AND_DECISIONS.md. Do not infer a permanent
model alias from older Math Skills PHP files. Require the operator to confirm
the available model; do not enable tools/web/files for this tutor adapter.

For compatible chat-completions JSON mode: request a JSON object and include
the literal word JSON plus a schema-shaped example in the prompt. Parse the
returned content with size/depth limits and validate locally. JSON output mode
does not establish schema compliance, mathematics or proper state. Reject
empty content, truncation and invalid envelopes. Provider refusal/timeout is a
system issue. Provider credentials/response headers/reasoning internals remain
private. The renderer receives only accepted public projections.

## Request assembly order

System architecture and immutable adapter rules → accepted binding exchange →
trusted canonical curriculum/capabilities → accepted state and exact current
event → item and checker result as separate trusted records → bounded relevant
transcript with learner text clearly data. Keep all trust labels outside
learner-controlled strings. Never append learner input to a system instruction.

Use prompts/ as templates. The provider has no filesystem or network tools.
resource_requests contain canonical IDs and purpose, not paths or executable
instructions. Host validates them and may issue another bounded call.

## Private envelope

contracts/model-envelope.schema.json defines architecture v1.1 fields. For a
QUESTION, item is a draft with item_id/version supplied by host in context;
the model must copy them. The host checks and mints the accepted item identity.
student_message may contain a short explanation/feedback, but the task prompt
is displayed from the accepted item record. No duplicate unvalidated prompt
hidden in narrative. Host supplies the correctness sentence from its verdict
template. Model commentary must not reverse/overstate the verdict.

Model evidence_proposals are not ledger facts. The host attaches event IDs and
scopes accepted observations/hypotheses. Promotion counters, review status,
checker results and acceptance receipts are host-produced only.

REPORT envelopes propose wording only. ReportBuilder reconstructs claims from
accepted events. In account-free v1 the owner can export the session report;
no separate privileged teacher channel is invented. Do not show internal
diagnostic hypotheses in ordinary student feedback.
