<?php
declare(strict_types=1);

namespace AlgebraEngine\Tutor;

/**
 * A problem spec that code cannot accept. The code names the rule; the
 * message is written for the repair request the AI receives (never shown to
 * the learner).
 */
final class SpecError extends \RuntimeException
{
    public function __construct(public string $reason, string $message)
    {
        parent::__construct($message);
    }
}
