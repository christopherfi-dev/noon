<?php
declare(strict_types=1);

namespace AlgebraEngine\Workflow;

/** A request the host refuses; carries the HTTP status and a stable error code. */
final class EngineError extends \RuntimeException
{
    public function __construct(
        public readonly int $httpStatus,
        public readonly string $errorCode,
        string $userMessage,
    ) {
        parent::__construct($userMessage);
    }
}
