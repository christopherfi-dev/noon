<?php
declare(strict_types=1);

namespace AlgebraEngine\Provider;

interface ProviderAdapter
{
    /** 'mock' or 'openai_compatible' */
    public function name(): string;

    public function model(): string;

    /**
     * One non-streamed chat completion. JSON mode is off for the binding call
     * and on for envelope calls. Round 6 §3.6: $reasoning is the setting code
     * chose for this call (thinking enabled or disabled, and the effort);
     * null means the configured default.
     *
     * @param list<array{role:string,content:string}> $messages
     * @param array{thinking:string,effort:?string}|null $reasoning
     */
    public function complete(array $messages, bool $jsonMode, ?array $reasoning = null): ProviderResponse;
}
