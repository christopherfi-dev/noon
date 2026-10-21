<?php
declare(strict_types=1);

namespace AlgebraEngine\Provider;

final class ProviderResponse
{
    /**
     * @param ?string $error TIMEOUT | NETWORK | HTTP_ERROR | TOO_LARGE | BAD_RESPONSE, or null on transport success
     * @param array{prompt_tokens?:?int,completion_tokens?:?int,reasoning_tokens?:?int} $usage token counts only; reasoning text is never kept
     */
    public function __construct(
        public readonly ?string $content,
        public readonly ?string $finishReason = 'stop',
        public readonly ?string $error = null,
        public readonly ?int $httpStatus = null,
        public readonly array $usage = [],
        public readonly ?int $durationMs = null,
    ) {
    }

    public static function failure(string $error, ?int $status = null): self
    {
        return new self(null, null, $error, $status);
    }
}
