<?php
declare(strict_types=1);

namespace AlgebraEngine\Tutor;

/**
 * Round 6 §2.4: the gradebook, read from the session's accepted ledger. For
 * every attempt it records the problem, the skill, practice or check, the
 * student's entry, any reading note, the verdict, and any help given between
 * presentation and answer. Code writes these events; nothing the AI returns
 * becomes one of them.
 *
 * Ledger events:
 *   PROBLEM_PRESENTED  problem_id, version, purpose, skill_id, source, kind, template_id, variant_id, prompt, fingerprint, retry_of
 *   HELP_GIVEN         problem_id, version, choice (HINT | NOT_LEARNED; EXPLAIN and TYPED_STUCK in round 6 records)
 *   ATTEMPT_RECORDED   problem_id, version, entry, read_as, reading_note, verdict, normalized, signatures, points_to, slip, response_sha256, scope
 *   PROBLEM_CLOSED     problem_id, version, reason (ANSWERED | SKIPPED | NOT_LEARNED | SET_ASIDE | SESSION_ENDED)
 *   CHECK_HELD         problem_id, version, reason (HELP_BEFORE_CHECK | HELP_DURING_CHECK): attempts on it are held
 *   ATTEMPT_HELD       attempt_id, problem_id, reason (VERDICT_CONFLICT): that attempt is held
 *
 * A held attempt keeps its verdict but counts neither for nor against a goal
 * (round 7 §5).
 */
final class Gradebook
{
    /**
     * @param list<array{event_id:string,type:string,data:array,created_at?:string}> $events
     * @return list<array<string,mixed>> attempts in order
     */
    public static function attempts(array $events): array
    {
        $problems = [];
        $help = [];
        $out = [];
        $heldProblems = [];
        $heldAttempts = [];
        foreach ($events as $e) {
            if ($e['type'] === 'CHECK_HELD') {
                $heldProblems[$e['data']['problem_id']] ??= $e['data']['reason'];
            } elseif ($e['type'] === 'ATTEMPT_HELD') {
                $heldAttempts[$e['data']['attempt_id']] ??= $e['data']['reason'];
            }
        }
        foreach ($events as $e) {
            $d = $e['data'];
            switch ($e['type']) {
                case 'PROBLEM_PRESENTED':
                    $problems[$d['problem_id']] = $d;
                    $help[$d['problem_id']] = [];
                    break;
                case 'HELP_GIVEN':
                    $help[$d['problem_id']][] = $d['choice'];
                    break;
                case 'ATTEMPT_RECORDED':
                    $p = $problems[$d['problem_id']] ?? null;
                    if ($p === null) {
                        break; // an attempt must belong to a presented problem
                    }
                    $out[] = [
                        'attempt_id' => $e['event_id'],
                        'problem_id' => $d['problem_id'],
                        'version' => $d['version'],
                        'skill_id' => $p['skill_id'],
                        'purpose' => $p['purpose'],
                        'variant_id' => $p['variant_id'],
                        'template_id' => $p['template_id'],
                        'prompt' => $p['prompt'],
                        'retry_of' => $p['retry_of'] ?? null,
                        'entry' => $d['entry'],
                        'read_as' => $d['read_as'],
                        'reading_note' => $d['reading_note'],
                        'verdict' => $d['verdict'],
                        'signatures' => $d['signatures'],
                        'points_to' => $d['points_to'] ?? [],
                        'slip' => $d['slip'],
                        'help' => $help[$d['problem_id']] ?? [],
                        'held' => $heldAttempts[$e['event_id']] ?? $heldProblems[$d['problem_id']] ?? null,
                        'at' => $e['created_at'] ?? null,
                    ];
                    break;
            }
        }
        return $out;
    }

    /** @return list<array{problem_id:string,skill_id:string,purpose:string,reason:string}> problems closed without an answer */
    public static function closedUnanswered(array $events): array
    {
        $problems = [];
        $out = [];
        foreach ($events as $e) {
            if ($e['type'] === 'PROBLEM_PRESENTED') {
                $problems[$e['data']['problem_id']] = $e['data'];
            } elseif ($e['type'] === 'PROBLEM_CLOSED' && $e['data']['reason'] !== 'ANSWERED' && isset($problems[$e['data']['problem_id']])) {
                $p = $problems[$e['data']['problem_id']];
                $out[] = ['problem_id' => $p['problem_id'], 'skill_id' => $p['skill_id'], 'purpose' => $p['purpose'], 'reason' => $e['data']['reason']];
            }
        }
        return $out;
    }

    /** @return array<string,array> problems presented this session, by id */
    public static function problems(array $events): array
    {
        $out = [];
        foreach ($events as $e) {
            if ($e['type'] === 'PROBLEM_PRESENTED') {
                $out[$e['data']['problem_id']] = $e['data'];
            }
        }
        return $out;
    }

    /** Help given on a problem so far (choices), from the ledger plus this turn's events. @param list<array> $events */
    public static function helpOn(array $events, string $problemId): array
    {
        $out = [];
        foreach ($events as $e) {
            if ($e['type'] === 'HELP_GIVEN' && $e['data']['problem_id'] === $problemId) {
                $out[] = $e['data']['choice'];
            }
        }
        return $out;
    }
}
