<?php
declare(strict_types=1);

namespace AlgebraEngine\Workflow;

use AlgebraEngine\Support\Ids;
use AlgebraEngine\Support\LearnerText;

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
    /** @var list<array{kind:string,text:string,source:string}> source HOST or MODEL (internal; never projected) */
    public array $messages = [];
    public ?array $generation = null;
    public string $triggerEventId;
    public ?array $lastResult = null;
    public ?array $lastItem = null;
    public ?array $learnerPayload = null;
    public int $modelCalls = 0;
    public bool $reportNeeded = false;
    /** Host-verified statements of what the last answer did (signature feedback). */
    public array $facts = [];

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

    /** @param string $source HOST for host-written lines, MODEL for accepted model text (round 5: B14 is checked against it) */
    public function say(string $kind, string $text, string $source = 'HOST'): void
    {
        // R5-B: host-written lines pass the same internal-vocabulary check as model text.
        if ($source === 'HOST' && ($terms = LearnerText::internalTerms($text)) !== []) {
            throw new \LogicException('Host line uses internal terms (' . implode(', ', $terms) . '): ' . $text);
        }
        if (trim($text) !== '') {
            $this->messages[] = ['kind' => $kind, 'text' => $text, 'source' => $source];
        }
    }

    /** Like say(), but placed at $index among this turn's messages (a line that depends on what follows it). */
    public function sayAt(int $index, string $kind, string $text, string $source = 'HOST'): void
    {
        $before = $this->messages;
        $this->messages = [];
        $this->say($kind, $text, $source);
        $this->messages = array_merge(array_slice($before, 0, $index), $this->messages, array_slice($before, $index));
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
