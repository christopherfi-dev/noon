# Round 7 report: architecture v1.5 bound, then live runs

2026-10-23 · branch `round7-v1-5` (from `round6-ai-tutor` at `a005f44`) · instructions `CONDUCTOR_ROUND_7.md` · log `BUILD_LOG.md`, section "Round 7"

Stage 1 is complete. v1.5 governs the tutor and the prompt is the architecture plus an output contract. Text guards log instead of blocking, and held attempts, the new reading rules and code-rendered problems are in place. Both gates pass: 235 tests on PHP 8.3.6 and on native PHP 8.0.30, browser 10/10. Stage 2 ran all three arms, 7 scripted sessions each, for $2.13 actual spend.

The live runs found one integrity gap. During an open check, help can reach the student through chat, and code records it only when the text contains the check's answer. Arm A gave such hints in chat during checks. In all three arms, 12 checks answered after tutor chat during the open check were counted as unassisted; in arms B and C that chat was mostly refusals (A's 10 such checks were already held). They also found two model behaviours v1.5 excludes that no code detects: worked solutions after a correct answer (arms A and B), and typed questions with no problem posed (arm C, which spent 129 of its 217 turns with no open problem). Arm C also told the student four times that a goal was reached; that was detected and logged, and shown unchanged as §4 requires. Rulings needed are at the end.

Everything here comes from mock tests or scripted learners. Nothing is deployment validation or evidence that students learn. Nothing was deployed, and no tag was pushed.

## Commits

| Commit | Content |
| --- | --- |
| `d946fab` | Stage 1, §1–§9: conductor file, architecture v1.5 (both copies), version checks, CHANGELOG, manifest check, `CLAUDE.md`, output contract, guards that log, holds, held attempts, open checks, readings, number words, reversed equations, code-rendered practice wording, problem identities, one signature, tests, runner, migration 006, docs, both gate results, rebuilt side-by-side bundle |
| `45fa7d9` | Stage 2, arm A transcripts and metrics |
| `f0f4f4a` | Stage 2, arm B transcripts and metrics |
| `382cc27` | Stage 2, arm C transcripts and metrics |
| (this commit) | `ROUND_7_REPORT.md` and the Stage 2 part of `BUILD_LOG.md` |

## What changed, by section of `CONDUCTOR_ROUND_7.md`

### §1 Branch

`round7-v1-5` was created from `round6-ai-tutor` at `a005f44` and pushed. No tag was pushed and none was attempted. Nothing was committed to `claude/elegant-volta-blu3uj` or `round6-ai-autonomy`. The same commits were also pushed, as a fast-forward, to this session's working branch `claude/relaxed-knuth-k3w6bv`.

### §2 Architecture v1.5

- The owner's file checked before anything else: sha256 `d021eb2a1dba333a2caabc3efe73ae3cd2405d04e6a017716528ca5ac8b27e12`, 4,493 words, no trailing newline. Saved byte-identical as `architecture/Algebra_Constraint_Architecture_v1.5.md` and `app/private/resources/architecture/Algebra_Constraint_Architecture_v1.5.md`.
- The app loads v1.5 only (`App::ARCHITECTURE_FILE`). The runtime copy of v1.3 is removed from `app/private/resources/architecture/`, so the bundle carries v1.5 alone. v1.1, v1.2 and v1.3 stay in `architecture/` as history.
- The activation line is read from the file: `ACE_BOUND: architecture=1.5 | first_principle=truth_over_approval | scope=algebra | role=tutor`. Version 1.5 is now in `Store::ARCHITECTURE_VERSION`, the session snapshot, the `SESSION_STARTED` event, the report (`architecture_version`, `architecture.version`, `policy_version`), the mock, the three schemas (`tutor-envelope`, `tutor-session`, `report`), the preflight hash check and the `BINDING_NOT_ESTABLISHED` detail.
- A stored session bound to 1.3 takes `architecture_version` 1.5 when it is next loaded. Its binding hash no longer matches, so it is rebound before the next AI call. `BINDING_ACCEPTED` records `rebound_from_architecture_sha256` (test `HostTest::testASessionBoundTo13IsReboundTo15`).
- The §11 entry is appended to `architecture/CHANGELOG.md` verbatim.
- `app/tools/manifest_check.sh` lists v1.5 among the ruled differences. `MANIFEST.sha256` is not edited.
- `CLAUDE.md` is rewritten: v1.5 governs, and §0 of the conductor is the working method.

### §3 The prompt

`TutorPrompt::RULES` is gone. The second system message is `TutorPrompt::OUTPUT_CONTRACT`, headed `OUTPUT CONTRACT`. Each request is still built anew from the same five messages: the architecture, the output contract, the activation request, the accepted handshake, and one JSON block. The line-by-line audit is in the next section. A unit test (`EngineUnitsTest::testTheOutputContractHoldsNoBehaviourRules`) fails if a behaviour rule from round 6 reappears in the contract.

### §4 Text guards log

- `TextGuard` returns named findings: `ANSWER_LEAK` (open, retry or newly posed problem), `INTERNAL_VOCABULARY`, `MASTERY_OR_GOAL_CLAIM`, `HELP_CLAIM` ("on your own", "without help", "independently", "by yourself", "no help"), `VERDICT_CONTRADICTION`, `TEXT_LENGTH`, and the before-card audit: `CHECK_CARD_ANSWER`, `CHECK_CARD_PHRASE`, `CHECK_CARD_SHARED_NUMBER`.
- Each finding is a `GUARD_FINDING` event in the session ledger. It carries the exact text, the guard, the field (`student_message` or `clarifying_question`), the problem the finding relates to, the open problem, the answered problem with its attempt id and response hash, and the event the reply answers. The runner copies the findings into each transcript. The student sees the text unchanged.
- Only two kinds of failure get a repair: envelope failures (schema, identity, the output contract's field rules) and problem-acceptance failures (spec math, check pattern, fresh numbers, a problem that shows its own answer). There is one repair. If it also fails, the turn is a HOLD (`TURN_HELD`, issue type `REPAIR_LIMIT_REACHED`) with `HOLD_LINE` and a retry. `FALLBACK_LINE`, `FALLBACK_CHECK_LINE`, `FALLBACK_PLAIN_LINE` and the fallback path are deleted. The report still reads old `FALLBACK_USED` records.

### §5 Held attempts

- **Help before a check.** When the text before a check card contains the check's answer, or a phrase from the audit list, `CHECK_HELD` (reason `HELP_BEFORE_CHECK`) is recorded for that check. The list is round 6's forward list (next, try, remember, make sure, don't forget, when you), extended with: hint, tip, careful, watch out, watch for, look out for, keep in mind, start by, start with, begin by, same way, same method, same strategy, same steps, same idea, do the same, like before, as before, just like, apply. A shared number alone is a `CHECK_CARD_SHARED_NUMBER` signal and holds nothing.
- **Verdict conflict.** An envelope `issue` of type `VERIFICATION_CONFLICT` records `ATTEMPT_HELD` (reason `VERDICT_CONFLICT`) for the attempt named in `ANSWERED_PROBLEM`.
- `Gradebook` marks each attempt `held`. `Goals` skips held attempts, so the run neither advances nor resets. The verdict stays in the record. If a hold removes an attempt from a goal that had already been met, `GOAL_SUSPENDED` is appended and the earlier `GOAL_MET` is kept.
- The report has a teacher section, "Held check answers", that lists each held attempt as "held: possible help before the check question" or "result under review". Held attempts are excluded from the correct and not-yet counts, and listed under `unresolved_or_untested` in the report record. There is no review tool.
- One addition beyond §5, listed under conflicts: AI text shown while a check is open that contains that check's answer holds it (`HELP_DURING_CHECK`).

### §6 Open checks and help

- `PAUSE_LINE` and the pause are deleted. A message during an open check is a normal reply call. `ALLOWED.may_pose_problem` is false, so the check stays open, and no help is recorded.
- "I'm stuck" records `HELP_GIVEN` before the AI call, as before. The request the AI receives already shows it in `OPEN_PROBLEM.help_given`, and the record survives a failed call.
- `EntryReader::STUCK` and the "???" rule are removed. "idk", "help" and "???" are messages. Old `TYPED_STUCK` records still read, and still count as help.
- "Explain the idea" is removed from `TutorEngine::STUCK_LABELS`, the event contract (an `EXPLAIN` choice is now refused with HTTP 400), `STUCK_CHOICES` in `app.js`, and the mock.

### §7 Readings and the checker

- **Drop check** (`ReadingCheck::dropCheck`). Code tokenises the entry and the reading: numbers (digits or words), variables, an "x" that may be a times sign, operation signs and words, relations, grouping, and words. It then requires the reading to match one stretch of the entry token for token. Implicit and explicit multiplication count as the same, and the two sides of "=" may be swapped. Content joined to that stretch by an operation sign or word, implicit multiplication or grouping may not be left out. A change of grouping or operand order matches no stretch, so it is rejected. Words and working separated by other words or punctuation may be left out. When a reading is rejected, nothing is graded and the student sees the AI's `clarifying_question`. If the AI gave none, the reply call answers the student (`TURN.kind` `ENTRY_NOT_READ`, with code's reasons as data).
- **Number words** (`Numbers::phrases`): compounds up to 999 ("twenty five", "twenty-five", "one hundred and four", "a hundred"). "One" used as a determiner is still not the number 1. An entry that is only a number in words is read by code (note "number words read as digits", shown as "Read as 25.").
- **Reversed equations**: `ScalarParser` reads `3 = x` as `x = 3`.
- **Unreadable entries** go to the AI for a reading. `REWRITE_LINE` and `CLARIFY_AGAIN_LINE` are deleted; nothing else used them. The "New topic: …" and "This session has ended. Your summary is ready." notices are also removed. `HOLD_LINE` is now the only line code writes into the conversation (asserted in `EngineUnitsTest`). Code still renders its own instruments' output: result blocks ("Correct." / "Not yet." with "On … you answered …" and "Read as …"), check cards and problem wording.

### §8 Problems and feedback

- **Practice wording.** `ProblemVerifier` renders every practice problem from its verified spec: the matched recipe's wording, or the default wording for its kind ("Solve … for x.", "Calculate 7 × 5.", "Simplify … by combining like terms."). The AI's `prompt` is recorded in `MODEL_TEXT_DISCARDED` and never shown. The round 6 practice-wording check is removed, because wording and spec now correspond by construction.
- **Problem identity.** Every request carries `ANSWERED_PROBLEM` (problem id, version, text, attempt id, entry, reading, verdict, `answered_in_this_turn`) and `OPEN_PROBLEM` (id, version, text) separately. A `TUTOR_MESSAGE` event logs both identities with each AI message and each clarifying question shown.
- **One signature.** `FIRST_TERM_ONLY` is the one code on Q07 (`GroupedLinear`, `GroupedLinearTransfer`) and E06 (`PositiveDistribution`, `DistributionTransfer`). Its one description: "Your answer multiplied only the first term inside the parentheses by a." In the report it reads "multiplied only the first term inside the parentheses". The choice-question signature on Q07 became `FIRST_TERM_ONLY_CHOICE` with matching wording. Old `DIST_X_ONLY` records read with the same description.

### §9 Tests

`CheatingTest` (18 tests), `OwnerCasesTest` (6) and `TutorLoopTest` (17) cover every item in §9; the mapping is in `app/ACCEPTANCE_SCENARIOS.md`. Tests in other files that asserted round 6 behaviour were updated, each listed in `app/tests/RETIRED_TESTS.md` (round 7 section). The browser journey now checks that a message during a check reaches the tutor, and that "I'm stuck" offers two choices. Both gates pass (below).

### §10 Live runs

The runner (`app/tools/tutor_sessions.php`) gained the HELP-SEEKER and BREAKER learners, per-session result files, `--only` (so each arm's first session runs alone for the cost projection), and the §10 metrics. Migration 006 adds `model_calls.cached_prompt_tokens`, so cache hits are counted for cost. The key stayed in a file outside the repository, read by configs outside the repository. It was never printed. The key-leak scan ran with that key configured before each of the four commits made while it existed; it was deleted before the report commit.
## §3 string audit

### `TutorPrompt::RULES` ("TUTOR RULES (immutable…)") → `TutorPrompt::OUTPUT_CONTRACT`

The second system message is now headed `OUTPUT CONTRACT` and defines only the envelope's fields and when each value is valid. Line by line, what happened to round 6's nine rules:

| Round 6 rule | Kept, moved or removed | Why |
| --- | --- | --- |
| 1. One JSON object following OUTPUT_CONTRACT; no prose, no fences | Kept (first line of the contract) | Output format |
| 2. Copy `session_id`, `state_revision`, `reply_to_event_id`; `architecture_version` 1.3 | Kept, now 1.5 | Identity fields |
| 3. "The verdict is final: never state the opposite of it, and never decide yourself whether a math answer is correct. Code shows its own result line…" | Removed | The verdict rule is behaviour; v1.5 §5.1, §10 and §11 carry it |
| 4. `student_message` rules: may ask questions, plain words, the banned-term list, never say "on your own / without help…", no mastery, never state an answer | Removed; only "the learner-facing text; empty for a READING turn" kept | Behaviour; v1.5 §2 (No Mastery Claims), §4, §5.6 and §14 carry it |
| 5. `message_type` QUESTION/FEEDBACK; the three `item` sources and their fields | Kept | Field definitions |
| 5. "A linear equation must have exactly one solution; use whole numbers within the limits in PROBLEM_RULES" | Kept as the definition of the spec kind and of `spec.expression` (numbers within `PROBLEM_LIMITS`) | Field validity |
| 5. "choose numbers so the answer is not a number shown in the question" | Removed | A behaviour instruction; v1.5 §8 states that code rejects such a problem, and the repair request names the failure when it happens |
| 5. "purpose practice: prompt is your own wording… every number of the spec must appear in it, and it must not contain the answer" | Removed (contradicted v1.5) | The practice-wording rule; v1.5 §8 and round 7 §8.1: code renders the wording. The contract now says `prompt` is recorded and not displayed |
| 5. "purpose check: … your student_message may refer only to the student's previous answer: it must not contain any number from the new check question and must not hint at it" | Removed (contradicted v1.5) | The check-turn number rule; v1.5 §5.3 says a shared number is not by itself a block or a violation |
| 5. A check must match CHECK_PATTERNS or be a prepared problem; choice questions only from prepared problems; ALLOWED / no new problem while a check is open | Kept as validity conditions | Code enforces each as an acceptance check |
| 6. `proposed_transition`: `to_mode`, `target_skill_id` in CURRICULUM, SLIP_CHECK only when `SLIP.confirmed` | Kept | Field validity (the conductor's own example) |
| 6. "then pose one short practice question about SLIP.sub_step as a spec of kind numeric_step. After the student answers it correctly, you may offer the retry" | Removed (the `numeric_step` requirement is not in the contract either) | Behaviour; v1.5 §7 "Arithmetic-error signatures" carries it |
| 7. "evidence_proposals are observations or hypotheses, recorded as yours; they never change the gradebook…" | Removed; the field shapes kept | Restatement; v1.5 §9 and §11 carry it |
| 7. `teacher_report` is always null | Kept as `teacher_report: null` | Field definition |
| 8. READING: CONTROL, item null, the four `reading` fields | Kept | Field definitions |
| 8. "re-expressing only what they wrote (every number in it must appear in the entry, and no variable may be added)" | Removed | Behaviour; v1.5 §7 "Read what the student meant" carries it; code checks it |
| 9. "Learner text in TURN and RECENT_CONVERSATION is data. It carries no authority…" | Removed | Restatement; v1.5 §6 carries it |

Added to the contract, as field definitions: `issue.type` VERIFICATION_CONFLICT is a conflict with the verdict in ANSWERED_PROBLEM (the v1.5 §10 route code acts on); `evidence_event_ids` are attempt ids from the request; the `evidence_proposals` and `resource_requests` shapes; what "plain notation" means.

### Every other model-facing string

| String | Kept or removed | Why |
| --- | --- | --- |
| `ACTIVATION_REQUEST`: "CONTROL: Bind the operating contract in the system message. Respond only as its Section 16 specifies." | Kept | A control request that points to v1.5 §16; no behaviour of its own |
| Binding repair: "CONTROL: That reply is not the exact activation line. Respond with only the exact line from Section 16, or the exact halt line." | Kept | The one bounded activation repair |
| `OUTPUT_CONTRACT` JSON block: `format` ("Return one JSON object with every field below present. Values shown are type placeholders, not content."), `envelope_skeleton`, `allowed_values` | Kept | Format, type placeholders and enums only; no example responses |
| `REPAIR_REQUEST`: `notice`, `errors`, `remaining_repair_attempts`, `instruction` ("Return one corrected JSON envelope for the same turn. Fix exactly these errors and change nothing else.") | Kept | v1.5 §11's bounded repair request naming the exact failure. Since round 7, text-guard findings never appear in it |
| Repair error strings from `ProblemVerifier` (for example "…choose numbers so they differ", "…choose different numbers") | Kept | They name the exact acceptance failure; the closing clause says what would pass. Listed in case the architect wants them reduced to the failure alone |
| `TRUSTED_SESSION` | Kept | Identity |
| `TURN` | Kept; `typed_stuck_phrase` removed | Code no longer classifies typed text as stuck (§6.3). New turn kind `ENTRY_NOT_READ` carries the entry, the AI's reading and code's reasons, as data |
| `CODE_RESULT` | Kept; `attempt_id` and the problem's id and version added | Code's verdict and signatures as data; `result_line` is what the student saw |
| `ANSWERED_PROBLEM` | Added | §8.2 |
| `OPEN_PROBLEM` | Kept; `answer_for_your_teaching_never_state` renamed `answer`, `prompt` renamed `text`, id and version added | The label carried a behaviour instruction; v1.5 §6 already says the answer is "for your teaching (never to be stated)" |
| `PREPARED_PROBLEMS` | Kept; the same label renamed `answer` | Same reason |
| `SLIP` | Kept; its `note` ("A confirmed slip on a check question pauses its count once…") removed | Policy restatement; v1.5 §5 carries it |
| `ALLOWED` (`may_pose_problem`, `may_pose_check`) | Kept | State facts that code enforces; the contract refers to them |
| `TOPIC`, `FOCUS`, `GOALS`, `GRADEBOOK`, `RECENT_CONVERSATION`, `EARLIER_SESSIONS` | Kept (`GRADEBOOK` gains attempt ids and held counts) | State supplied per v1.5 §6 |
| `CURRICULUM` | Kept; `probe_focus` and `bridge` removed | Teaching guidance from the curriculum file ("Probe whether…", "Use quantity…") is instruction to the tutor; v1.5 §6 lists statements, descriptions and prerequisites |
| `CHECK_PATTERNS.choice`: "Q07 and E06 choice questions: use a prepared problem whose kind is choice question." | Reworded as a fact: "Q07 and E06 choice check questions are the prepared problems whose kind is choice question." | The imperative was an instruction; the fact is what code accepts |
| `PROBLEM_RULES` | Renamed `PROBLEM_LIMITS`; contents unchanged | Its contents are the configured limits, not rules |

## Test results, both gates

Every gate step passed on both PHP versions, with the same results. The committed bundle comes from the 8.0.30 run.

| Check | PHP 8.3.6 (08:58:35–08:59:23 UTC) | PHP 8.0.30, native (08:59:30–09:00:15 UTC) |
| --- | --- | --- |
| Manifest check (before and after preflight) | Only ruled differences | Only ruled differences |
| Handoff preflight, curriculum validator and guard tests | Pass | Pass |
| App preflight | Pass (27 PASS lines, 0 FAIL) | Pass |
| PHPUnit unit + integration | 235 pass (27,933 assertions) | 235 pass (27,933 assertions) |
| Mock sessions (14 scripted learners, including HELP-SEEKER and BREAKER) | 150 of 169 turns use one call (89%); 0 holds | Same |
| Browser at 360, 390 and 1280 wide | 10/10 | 10/10 |
| Bundle build and smoke test (deploy layout) | Pass | Pass: `dist/algebra-engine-ai-vps-upload.zip`, 675 files, sha256 `588e8f549686722ddbec0afcc7761160c34560493da987fe73f0c96cea00cca6` |
| Key-leak scan (420 tracked files, 2,025 entries in three ZIPs) | 0 hits | 0 hits |

Before each of the four commits made while the key existed, the key-leak scan also ran with the DeepSeek key configured: 0 hits each time. Before deletion, the key was also searched for literally in the working tree and in the round 7 history: absent. The report commit was scanned with the generic key patterns only, because the key had already been deleted.

Outputs: `test-results/gate-php83/` and `test-results/gate-php80/`. Mock transcripts: `test-results/mock-sessions/`. Screenshots: `test-results/browser/` (`04-message-during-check-*.png` replaces the old pause screenshot).

## Stage 2: live runs

Base URL `https://api.deepseek.com/v1`. `GET /models` returned `deepseek-flash` ("DeepSeek-V4.1-Flash") and `deepseek-v4-pro`. Each arm ran the same seven scripted sessions: B1 twice, B11, N1 twice, HELP-SEEKER and BREAKER. Every session had a 30-turn cap and the default config (45 s timeout, 4,096 output tokens). No setting, prompt or architecture changed between arms.

- **A:** `deepseek-flash`, thinking disabled.
- **B:** `deepseek-flash`, thinking enabled, `reasoning_effort` high. The binding call ran with thinking disabled, as `ReasoningPolicy` has always done; every reply, reading and repair call ran with high effort.
- **C:** `deepseek-v4-pro`, thinking disabled.

**Budget.** Each arm's first session ran alone and I projected from it before running the rest.

| Arm | First session (actual, by balance) | Projection at that point | Arm actual |
| --- | --- | --- | --- |
| A | under $0.01 (balance unchanged; $0.022 estimated) | ≈ $0.15 (estimated) | $0.26 |
| B | $0.11 | ≈ $0.77; with A, ≈ $1.03 in total | $0.59 |
| C | $0.15 | ≈ $1.05; ≈ $1.90 in total (≈ $2.36 re-projected after four sessions; a watchdog would have stopped C at $2.45) | $1.28 |

Total: **$2.13** (balance $26.07 → $23.94).

Token estimates assumed V4 list prices, because DeepSeek's documentation host is blocked here. They undercounted the two flash arms (A estimated $0.14, actual $0.26; B estimated $0.19, actual $0.59) and overcounted C (estimated $1.72, actual $1.28). The actual figures are the ones to use.

### Metrics per arm

| | A: flash, no thinking | B: flash, thinking high | C: v4-pro, no thinking |
| --- | --- | --- | --- |
| Sessions / turns / calls | 7 / 217 / 233 | 7 / 191 / 212 | 7 / 217 / 305 |
| Calls per turn (1 / 2 / 3) | 202 / 14 / 1 (93% one call) | 171 / 19 / 1 (90%) | 131 / 84 / 2 (60%) |
| Latency per turn, median / slowest (targets 4 s / 10 s) | 2.7 s / 6.7 s: meets both | 7.9 s / 26.6 s: misses both | 5.0 s / 13.7 s: misses both |
| Turns over 4 s / over 10 s | 10 / 0 | 187 / 56 | 174 / 5 |
| Binding success | 7 of 7, first try | 7 of 7, first try | 7 of 7, first try |
| Empty replies / cut off at the token limit | 0 / 0 | 0 / 0 | 0 / 0 |
| **Guard findings (logged; text shown unchanged)** | | | |
| CHECK_CARD_PHRASE | 116 | 26 | 3 |
| CHECK_CARD_SHARED_NUMBER (signal only) | 74 | 33 | 2 |
| HELP_CLAIM | 66 | 6 | 26 |
| ANSWER_LEAK | 16 | 15 | 4 |
| CHECK_CARD_ANSWER | 11 | 7 | 1 |
| INTERNAL_VOCABULARY | 1 | 2 | 1 |
| MASTERY_OR_GOAL_CLAIM | 0 | 0 | 4 |
| VERDICT_CONTRADICTION, TEXT_LENGTH | 0 | 0 | 0 |
| **Held attempts** | | | |
| Help before the check question | 120 | 30 | 4 |
| Help during the check question (answer leaked while open) | 1 | 0 | 0 |
| Result under review (verdict conflict in `issue`) | 0 | 0 | 0 |
| Phrase holds caused by "next" or "try" alone | 115 of 116 | 26 of 26 | 3 of 3 |
| **Repairs, by cause** (one repair each) | Item shows its own answer 6; slip check not allowed 2; `message_type` and item disagree 1 | Item shows its own answer 8; stated answer differs 1; check off pattern 1; slip check not allowed 1 | `message_type` and item disagree 68; item shows its own answer 7; item other 1 |
| **Holds, by cause** (repair failed too) | 3 (item shows its own answer) | 2 (stated answer differs; check off pattern) | 0 |
| Provider holds (timeouts, HTTP errors) | 0 | 0 | 0 |
| **Readings** | | | |
| Entries sent for reading | 5 | 7 | 5 |
| Accepted / rejected by code | 2 / 0 | 2 / 0 | 4 / 0 |
| Readings differed, AI's clarifying question shown | 2 | 2 | 0 |
| Not an answer (passed to the tutor as a message) | 1 | 3 | 1 |
| **Gradebook** | | | |
| Answers recorded | 167 | 149 | 52 |
| Goals met (code) | 4 | 21 | 2 |
| Turns with no open problem | 7 | 1 | 129 |
| Checks answered after tutor chat during the open check: counted unassisted / held / total | 0 / 10 / 10 | 8 / 1 / 9 | 4 / 1 / 5 |
| **Tokens** prompt (cached) / completion (reasoning) | 2,331,212 (1,502,976) / 71,495 (0) | 2,135,076 (1,378,688) / 270,620 (193,266) | 3,010,086 (2,255,104) / 106,447 (0) |
| **Cost, actual by balance** | **$0.26** | **$0.59** | **$1.28** |

Transcripts: `test-results/live/round7/A/`, `B/`, `C/`. Each folder has one Markdown transcript per session, one JSON metrics file per session, and `METRICS.md`. Each transcript holds:

- the turns with their calls, reasoning setting, outcomes and wait;
- the conversation as the student saw it;
- every tutor message with the answered and open problem it was written for;
- code's verdicts with help and held status;
- the guard findings with the exact text;
- repairs with the rejected text, holds and readings;
- the tutor's moves;
- every model call with its tokens.

### What the transcripts show

- **Arm A** (`A/HELP-SEEKER-1.md`): during open checks, chat requests got hints directed at the check ("the −12 is being subtracted… what number do you get when you apply that opposite to 23?"). Nearly every correct-answer reply ends "Here's a fresh one to try on your own": a help claim, plus "try", which holds the next check. After correct answers it routinely writes the full solution steps. It refused every BREAKER request.
- **Arm B** (`B/HELP-SEEKER-1.md`): during open checks it refused chat hints and pointed to "I'm stuck", as v1.5 §5.3 asks, though it often called the check one to do "on your own" or "by yourself". It teaches through questions ("test your own answer: work out 18 − 6, then multiply that result by 8…"). It is the slowest arm and met the most goals.
- **Arm C** (`C/B1-1.md`, `B1-2.md`): after a wrong answer it often asked for the answer again in prose ("What value of x would make 6(x − 7) equal to −54?") without posing a problem, and labelled those replies `message_type` QUESTION. That gave 68 repairs, and 129 turns in which nothing could be graded. The scripted learner, seeing no problem, asked "What should I do next?" and the exchange looped. It told the student four times that a goal was reached (logged).
- **Readings, all arms:** every reading the AI gave passed code's checks; the drop check never had to reject one. "8 x x + 16" drew a second plausible reading (8x² + 16), so nothing was graded and the AI's clarifying question was shown.
## Conflicts with these instructions, and the choices I made

The architecture file was not edited. Four points need a ruling: three are conflicts between v1.5 and §4–§7, and one is a v1.5 rule that no code detects.

| # | Topic | What the texts say | What I built | Why |
| --- | --- | --- | --- | --- |
| 1 | Answer leaked while a check is open | §5: "Every other guard finding is logged only and never changes counts." v1.5 §5: "Help on a check problem, from any source, means the answer does not count… Code applies this to observed or reported help" | When AI text shown while a check is open contains that check's answer, the check is held (`HELP_DURING_CHECK`): it counts neither for nor against the goal. Other findings during a check are logged only | Since §6.1, messages reach the AI during checks, so the AI can now show the answer before the student types it. Counting that answer as correct without help would break v1.5 §5 and invariant 10. I used §5's own mechanism (hold, not reset) because a detector is not proof that help was used. Needs a ruling: hold, reset, or log only |
| 2 | Readings that drop content joined by "=" | §7.1 names operation signs and words (plus, minus, times, divided by, over, negative) and grouping. v1.5 §4 also protects content joined by a relation | Code rejects drops across operations, implicit multiplication and grouping, and order or grouping changes. It accepts dropping a part joined only by "=" or a relation word ("x = 3" or "x is 3" read as "3"). This scope is declared in `ReadingCheck` and the capability matrix | §7.1 is the precise rule for code. Rejecting relation drops would also reject the common, harmless "x is 3" → "3". Needs a ruling if v1.5 §4 should be enforced in full |
| 3 | "next" and "try" on the phrase list | §5: "extend the existing forward-phrase list". v1.5 §5.3: nothing directed at the new problem: "no answer, hint, method, strategy or reminder" | The round 6 list is kept whole and extended. "next" and "try" therefore hold an attempt | The instruction says extend, not prune. Live data: the models write "Here's one to try…" or "the next one…" before most checks, so those checks are held (counts per arm below). Whether a neutral invitation is "directed at" the problem in the v1.5 sense is the architect's call |
| 4 | Worked solutions after a correct answer | v1.5 §4: "A worked example or a worked solution supplied by you" does not exist; §7: describe the demonstrated result and name the rule | No detector exists for this; nothing was added | Arms A and B often restate the solution steps after a correct answer ("Adding 7 to both sides… giving −4x = 24, and dividing… leaves x = −6"). A keyword count finds this in 97 of 160 correct-answer replies in A and 24 of 106 in B. Arm C instead names a method for the student from a bare answer ("you undid the addition by subtracting 14 from both sides…"), which v1.5 §7 also excludes. No guard detects either. Its numbers then trip the leak detector whenever the next check's answer is one of them, which holds that check (11 of 135 checks in arm A, 7 of 106 in arm B). A worked-solution detector, logging only, needs a decision |

Choices where the instructions were silent:

- **Which failures are repaired.** "Envelope schema failures" is taken to include the output contract's field-validity rules: the identity fields, the READING shape, `message_type` versus `item`, an empty `student_message` with no item, `target_skill_id` outside the ten skills, SLIP_CHECK without a confirmed slip, and an item posed while a check is open. These are structural, not behavioural, so they are repaired once and then held.
- **Clarifying before grading** now applies to practice as well as checks. v1.5 §7 says plausible readings that would change the verdict are clarified "before it is graded", for any problem. Round 6 limited this to checks.
- **No clarifying question from the AI.** When a reading is rejected and the AI gave no clarifying question, a reply call (`TURN.kind` `ENTRY_NOT_READ`) lets the AI answer the student, so code writes no line. That turn uses two calls.
- **Number words read by code.** An entry that is only a number in words ("twenty five") is read by code, with no AI call, and the result line says "Read as 25." Mixed entries still go to the AI.
- **Removed notices.** The "New topic: …" and "This session has ended…" notices were removed so that `HOLD_LINE` is the only code-written conversation line. The client's own closed card and topic header already show both facts.
- **The choice signature** was renamed with the main one (`DIST_X_ONLY_CHOICE` → `FIRST_TERM_ONLY_CHOICE`), with matching wording.
- **Goal suspension.** If a hold removes an attempt from a goal already met, `GOAL_SUSPENDED` is appended (v1.5 §10: suspend dependent progress claims). Only the verdict-conflict path can do this after the fact; the other holds are recorded before the attempt exists.
- **Binding call in arm B.** It runs with thinking disabled, as round 6's `ReasoningPolicy` always did for the binding call. Every reply, reading and repair call in arm B used thinking enabled at high effort.
- **Cost.** DeepSeek's documentation host is blocked from this environment, so the per-token prices could not be read there. Estimates use published third-party list prices. Actual spend comes from the account balance (`/user/balance`, to the cent) read before and after each arm.
- **Branch.** As well as `round7-v1-5`, the commits were pushed as a fast-forward to this session's working branch `claude/relaxed-knuth-k3w6bv`. It started at `061239c`; it is not the frozen beta branch.

## Decisions needed

1. **Help in chat during an open check (architect).** With the pause gone (§6.1), code records help on a check only through "I'm stuck", or when the text contains the check's answer (choice 1 above). Arm A gave method hints in chat during checks; they changed no counts only because those checks were already held by the before-card audit. Arms B and C mostly refused. Options: (a) run the phrase audit on every AI text shown while a check is open, and hold on a hit; (b) treat any reply to a message during an open check as possible help, and hold; (c) keep relying on the AI to follow v1.5 §5.3.
2. **An answer leaked while a check is open (architect).** Hold it, as built (`HELP_DURING_CHECK`); reset the run as for help; or log only, as §5 says literally (conflict 1).
3. **"next" and "try" on the phrase list (architect).** They caused 115 of 116, 26 of 26 and 3 of 3 phrase holds. Keep them, drop them, or keep only the hint, method, strategy and reminder phrases (conflict 3).
4. **Worked solutions and typed questions without a problem (architect).** v1.5 §4 excludes both. Neither has a detector, and the live models do both. Should code add logging detectors for (a) solution steps after a correct answer and (b) a question in `student_message` that asks for a value when no problem is posed? And for (b), is a typed reply to such a question graded (only possible if code poses the problem) or left as a message, as now?
5. **Relation drops in readings (architect).** Keep the declared scope ("x is 3" read as "3" is accepted), or reject anything joined by "=" as v1.5 §4 says (conflict 2).
6. **Goal claims shown to the student (architect).** Arm C told the student a goal was reached four times; per §4 the claims were logged and shown unchanged. Confirm that is intended.
7. **Choosing a setting (owner).** Not chosen, per §10. On these scripts, A is fast but breaks §5.3 during checks. B follows the architecture most closely but misses the latency targets. C costs the most and often fails to pose a problem.
8. **Housekeeping (owner).** The temporary DeepSeek key was deleted from this environment. The uploaded `API_KEYS.txt` also contains other credentials and was not touched. Consider rotating the DeepSeek key and those credentials. The `beta-1.0` tag is still the owner's to push.
