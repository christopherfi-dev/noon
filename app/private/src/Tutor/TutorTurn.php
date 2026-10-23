<?php
declare(strict_types=1);

namespace AlgebraEngine\Tutor;

use AlgebraEngine\Support\Ids;
use AlgebraEngine\Support\LearnerText;

/**
 * Working copy of one learner turn. Nothing here is durable until the engine
 * commits it in a single short transaction.
 */
final class TutorTurn
{
    /** @var list<array{event_id:string,type:string,data:array}> */
    public array $events = [];
    /** @var list<array> problem records to store */
    public array $problems = [];
    /** @var list<array> checker results bound to problem version, attempt event and response hash */
    public array $results = [];
    /** @var list<array{purpose:string,outcome:string,thinking:string,effort:?string,reason:string,duration_ms:?int}> */
    public array $calls = [];
    public bool $reportNeeded = false;
    public string $triggerEventId;

    public function __construct(
        public array $snap,
        public array $eng,
        public \DateTimeImmutable $now,
        public string $action,
    ) {
        $this->triggerEventId = Ids::make('evt');
    }

    public function event(string $type, array $data, ?string $eventId = null): string
    {
        $eventId ??= Ids::make('evt');
        $this->events[] = ['event_id' => $eventId, 'type' => $type, 'data' => $data];
        return $eventId;
    }

    /**
     * Add a conversation line. role tutor (the AI), student, or app (code).
     * Code lines pass the same internal-vocabulary check as AI text.
     */
    public function line(string $role, string $kind, string $text, ?string $result = null): void
    {
        if ($role === 'app' && ($terms = LearnerText::internalTerms($text)) !== []) {
            throw new \LogicException('Code line uses internal terms (' . implode(', ', $terms) . '): ' . $text);
        }
        if (trim($text) === '') {
            return;
        }
        $seq = ($this->eng['line_seq'] ?? 0) + 1;
        $this->eng['line_seq'] = $seq;
        $this->eng['conversation'][] = ['id' => 'c' . $seq, 'role' => $role, 'kind' => $kind, 'text' => $text, 'result' => $result];
        if (count($this->eng['conversation']) > 80) {
            $this->eng['conversation'] = array_slice($this->eng['conversation'], -80);
        }
    }

    /** Ledger events so far plus this turn's, for computing from the gradebook mid-turn. */
    public function allEvents(array $committed): array
    {
        return array_merge($committed, array_map(fn ($e) => $e + ['created_at' => null], $this->events));
    }
}
