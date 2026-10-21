<?php
declare(strict_types=1);

namespace AlgebraEngine\Workflow;

use AlgebraEngine\Support\Json;

/** Enforces contracts/workflow-policy.json for host and model-proposed transitions. */
final class TransitionGuard
{
    private array $policy;

    public function __construct(string $policyPath)
    {
        $this->policy = Json::decode((string) file_get_contents($policyPath));
    }

    public function states(): array
    {
        return $this->policy['states'];
    }

    /**
     * @param array{explicit_target_change?:bool,end_session?:bool} $ctx
     */
    public function allowed(string $from, string $to, array $ctx = []): bool
    {
        if (!in_array($from, $this->policy['states'], true) || !in_array($to, $this->policy['states'], true)) {
            return false;
        }
        if ($from === 'SESSION_CLOSED') {
            return false;
        }
        if ($from === $to) {
            return true;
        }
        if (in_array($to, $this->policy['allowed_edges'][$from], true)) {
            return true;
        }
        if ($to === 'SESSION_REPORT' && ($ctx['end_session'] ?? false)) {
            return true;
        }
        if (($ctx['explicit_target_change'] ?? false) && in_array($to, $this->policy['explicit_target_change_from_active_states'], true)) {
            return true;
        }
        return false;
    }

    public function assert(string $from, string $to, array $ctx = []): void
    {
        if (!$this->allowed($from, $to, $ctx)) {
            throw new StateConflict("Transition $from -> $to is not permitted");
        }
    }
}
