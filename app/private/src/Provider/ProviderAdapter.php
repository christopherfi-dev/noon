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
     * and on for envelope calls.
     *
     * @param list<array{role:string,content:string}> $messages
     */
    public function complete(array $messages, bool $jsonMode): ProviderResponse;
}
