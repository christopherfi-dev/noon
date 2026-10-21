<?php
declare(strict_types=1);

namespace AlgebraEngine\Workflow;

use AlgebraEngine\Support\Ids;

/**
 * Working copy of one committed instructional mutation. Nothing here is
 * durable until the engine commits it in a single short transaction.
 */
final class Turn
{
    /** @var list<array{event_id:string,type:string,data:array}> */
    public array $events = [];
    /** @var list<array{record:array,fingerprint:string}> */
    public array $items = [];
    /** @var list<array<string,mixed>> */
    public array $results = [];
    /** @var list<array{kind:string,text:string}> */
    public array $messages = [];
    public ?array $generation = null;
    public string $triggerEventId;
    public ?array $lastResult = null;
    public ?array $lastItem = null;
    public ?array $learnerPayload = null;
    public int $modelCalls = 0;
    public bool $reportNeeded = false;

    /**
     * @param array<string,mixed> $snap session.schema snapshot
     * @param array<string,mixed> $eng private engine state
     */
    public function __construct(
        public array $snap,
        public array $eng,
        public readonly \DateTimeImmutable $now,
        public readonly string $action,
    ) {
        $this->triggerEventId = Ids::make('evt');
    }

    public function event(string $type, array $data, ?string $eventId = null): string
    {
        $eventId ??= Ids::make('evt');
        $this->events[] = ['event_id' => $eventId, 'type' => $type, 'data' => $data];
        return $eventId;
    }

    public function say(string $kind, string $text): void
    {
        if (trim($text) !== '') {
            $this->messages[] = ['kind' => $kind, 'text' => $text];
        }
    }

    public function mode(): string
    {
        return $this->snap['mode'];
    }

    public function skill(): ?string
    {
        return $this->snap['current_skill_id'];
    }

    public function stackEmpty(): bool
    {
        return $this->snap['return_stack'] === [];
    }
}
