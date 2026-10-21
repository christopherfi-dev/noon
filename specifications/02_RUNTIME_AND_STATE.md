# 02 — Runtime, accepted state and transactions

## Host-owned services

Implement a controller, CurriculumRepository, PromptAssembler, ProviderAdapter,
ItemFactory/AcceptanceGate, ResponseCheckerRegistry, OrientationSelector,
TransitionGuard, EvidenceLedger, ReportBuilder, and SessionRepository. Names
can change; responsibilities and trust boundaries cannot.

## Session model

Use contracts/session.schema.json as the persisted snapshot. Retain an append-
only event ledger, immutable item versions, server checker results, and report
versions. Snapshots are rebuildable from accepted events. Store UTC times.
accepted_state.revision changes exactly once per committed instructional mutation,
including a hint; internal provider attempts do not advance it. Public task
creation has its own idempotency record and does not change learning state.
Forget deletes the session rather than advancing its learning revision.

Separate original target, current repair skill, target episode, workflow mode,
issue overlay and return stack. Stack frames carry parent skill, parent mode,
edge type/condition, triggering event and episode. Never mutate a parent into
the prerequisite. Maximum depth eight is a routing budget, not an assertion
that the foundation floor has been reached.

Each target episode has its own success evidence. Topic changes preserve a
suspended episode; an explicit resume can restore it. Returning to an unrelated
topic does not reuse old streaks. A correct prerequisite item never advances
the parent's count. Invalidating an event recomputes all dependent counts and
claims from the ledger; do not merely decrement the latest counter.

## Independence and counters

Item independence is host state. Hint/step/solution exposure, guided item role,
and a reused solved instance exclude the item from independent criteria.
Unrelated prior teaching alone does not disqualify a fresh item. Attach all
support events to their affected item/version. Rewording a clarification must
not silently add solution-bearing support.

Three consecutive correct independent accepted items with required variation
meet the default immediate criterion. Track coverage, not just a number.
An accepted incorrect independent check resets the current streak. Assisted,
partial, skipped, not learned or unscorable work neither advances nor resets
the streak by itself; it remains a traceable intervening event. A new independent
failure cycle begins only after diagnosis/instruction following the last failed
cycle. After three failed repair cycles at one point, offer pause/routing.
Orientation, transfer and retention evidence have separate counters.

## Allowed state transitions

Use architecture section 10 and contracts/workflow-policy.json. A model proposal
must be allowed from current mode and satisfy the named evidence preconditions.
No direct jump from wrong answer to IMMEDIATE_CRITERION_MET. An issue HOLD
does not replace the workflow mode. End is allowed in every active state and
produces a partial report. Closed sessions require explicit RESUME_SESSION,
valid retained resources and a new accepted event. No retention claim without
an actual later independent check and timestamps.

## Mutation algorithm (including asynchronous provider time)

1. Validate cookie ownership, origin/CSRF, body size and action schema.
2. Look up idempotency key scoped to this session. Same request hash: return
   cached completed response or 202 pending. Different hash: 409 conflict.
   Do this before rejecting the old revision of an exact retry.
3. In a short SQLite transaction compare expected_revision, active item/version
   and pending-operation status. Claim an operation with an unguessable lease
   token, base revision, expiry and request hash; record unique idempotency key.
4. Commit, release DB lock and PHP session file lock before network I/O.
   Never hold a SQLite write transaction while waiting on the provider.
5. Parse/check response and build drafts outside the transaction. Item/checker
   identities are host minted. Load bounded canonical curriculum on the server.
   One operation can include at most the original model call plus two repair
   calls. Provider timeout or truncation cannot count as a student failure.
6. Reopen a short transaction. Verify lease token, unexpired ownership and
   unchanged base revision. An abandoned operation's late result is discarded.
7. Append the accepted input/checker/support events, validated model proposals,
   accepted item (if any), and resulting snapshot atomically. Update revision
   once, store projected public response, and finish idempotency record.
8. Return only the public projection. A page reload obtains accepted state or
   pending-operation status. Never replay a response as a second scored attempt.

STOP/END_SESSION must remain usable while a provider call is pending: cancel
the lease and commit a partial report using accepted plus explicitly retained
pending input where its checker result was already durably accepted. If no
accepted verdict exists, mark the submitted answer pending/unscored. Late model
output cannot reopen the session. A cancel/restart must preserve the request
record so retries do not double bill or double score. Implement tests for this.

## Resource loading

Canonical source is curriculum/algebra-map.json. On every model call select
current target, repair skill, return parents, relevant candidate dependencies
and required skill records. Bound records/tokens; expand one dependency frontier
through a validated resource request, not the whole transitive map by default.
Check requested IDs and edges against canonical data. The host resolves
all_of, alternative paths and conditional edges; alternatives are not joint
requirements and conditions must match actual item features. No file paths
from client or model are opened. Rebuild the whole active block on each call.

Accepted state/evidence summaries are generated from the ledger, not copied
from a model-written memory paragraph. Prior assistant text is historical
content, never a new system authority. Preserve unresolved return frames when
truncating transcript. Save architecture/curriculum identity with each event.

## Defaults (operator-configurable within explicit server limits)

- Session: 20 minutes by default; task setup 10–60 minutes; offer continuation
  at ten target-assessment items or duration boundary, with a hard 60-minute
  episode budget. Time is an interaction limit, not a speed assessment.
- Orientation: 6 presented items, absolute 8; output repair attempts 2;
  diagnostic probes per decision 3; prerequisite depth 8; failed repair cycles 3.
- Request: 16 KiB JSON body; text answer 2,000 characters; optional work 4,000;
  provider result 64 KiB; 8,000 context tokens as an initial configurable budget.
- Provider connect timeout 10s, overall 45s, lease 90s. Return 202 only for an
  operation the host can actually resume/poll; no background worker assumed.
  In the simple synchronous deployment the originating request performs work;
  concurrent retries poll state. Expired leases are recoverable through a new
  idempotency key after explicit cancellation, never concurrent unchecked work.
- Begin with server-side request/cost budgets and at most one model operation
  per session. Implement global spend controls before public live-provider use.
