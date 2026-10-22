<?php
declare(strict_types=1);

namespace AlgebraEngine\Checking;

/** Result of a deterministic response check before it is bound to an item and event. */
final class CheckOutcome
{
    /** @param list<string> $limitations */
    public function __construct(
        public string $verdict,
        public string $observation,
        public string $scope,
        public ?string $clarification = null,
        public ?string $normalizedResponse = null,
        public array $limitations = [],
        public ?string $formIssue = null,
    ) {
    }
}
