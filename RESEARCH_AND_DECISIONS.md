# Research and implementation decisions

Curriculum research is preserved in curriculum/curriculum-sources.json and
its guides. It uses primary Common Core standards, Illustrative Mathematics
course material and the IES algebra teaching practice guide. Crosswalks and
prerequisite/pacing decisions remain scoped author judgments, not certification
or experimentally validated diagnostic routes.

Technical documentation checked 2026-10-21:

| Primary source | Consequence for this handoff |
|---|---|
| https://api-docs.deepseek.com/ | Configurable OpenAI-compatible chat adapter; operator confirms available model rather than copying an older alias |
| https://api-docs.deepseek.com/guides/json_mode/ | JSON object mode is followed by local schema/semantic checks; empty/truncated output is handled |
| https://www.php.net/supported-versions.php | PHP 8.3-compatible source; prefer a supported 8.4/8.5 host when available; verify the actual plan |

DeepSeek JSON documentation describes the JSON-object response setting,
explicit JSON prompting, truncation risk and occasional empty content. Those
features do not certify this engine's mathematics, schema or transitions.
Binding uses a separate text-mode request so the exact architecture handshake
does not conflict with JSON-only output settings. This two-call activation is
our implementation decision, not a provider-guaranteed constraint mechanism.

The PHP support page lists version support windows; it does not establish what
Bluehost provides for this account. All hosting extensions, network access,
permissions and execution limits remain deployment checks. A PHP session
security manual fetch timed out, so no claim of having reviewed that page is
made. The session controls in this specification are explicit design requirements.

No paid service pricing, provider retention assurance, school approval, Noon
curriculum equivalence or learning-effectiveness claim is made. Claude Code
must recheck the technical docs if implementation occurs after provider changes.
