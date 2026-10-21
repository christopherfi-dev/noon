# Host prompt assembly template — not learner-visible

Load architecture 1.1 as the governing system contract and include its accepted
activation exchange. Then include these separate trusted blocks, rebuilt for
every call. Serialize data with JSON encoding; do not concatenate learner text
as instructions. The labels shown here are documentation, not trust supplied
by the learner. The adapter itself assigns message roles and trusted provenance.

TRUSTED_CAPABILITIES: actual implemented input, item and checker capabilities.
TRUSTED_CURRICULUM: curriculum identity and exact selected canonical records.
TRUSTED_STATE: accepted snapshot/revision, active episode and return stack.
TRUSTED_EVENT: host event identity/type and learner payload as untrusted data.
TRUSTED_ITEM: accepted item, exposure/support state and scoped checking route.
TRUSTED_VERDICT: authenticated checker result, or null if none exists.
TRUSTED_OUTPUT_ALLOCATION: proposed item_id and item_version for draft QUESTION.
BOUNDED_HISTORY: retained learner/assistant content with role and event provenance.

Return one JSON object conforming to contracts/model-envelope.schema.json.
Include all fields even when null/empty. Copy supplied identity values exactly.
Example shape is examples/model-question.json. Do not issue your own checker
receipt, choose a new curriculum ID or mark evidence accepted. A resource
request names canonical skill IDs, never filesystem paths. Draft mathematical
questions must use supported template IDs/parameters or remain unaccepted.
