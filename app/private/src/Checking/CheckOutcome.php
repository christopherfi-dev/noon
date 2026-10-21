<?php
declare(strict_types=1);

namespace AlgebraEngine\Checking;

/** Result of a deterministic response check before it is bound to an item and event. */
final class CheckOutcome
{
    /** @param list<string> $limitations */
    public function __construct(
        public readonly string $verdict,
        public readonly string $observation,
        public readonly string $scope,
        public readonly ?string $clarification = null,
        public readonly ?string $normalizedResponse = null,
        public readonly array $limitations = [],
        public readonly ?string $formIssue = null,
    ) {
    }
}
