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
 *   HELP_GIVEN         problem_id, version, choice (HINT | NOT_LEARNED from "I'm stuck"; HELP | HINT with source TUTOR_MOVE from a
 *                      help move during an open check, round 9 §3.3; EXPLAIN and ANSWER_QUESTION in round 8 records; EXPLAIN and
 *                      TYPED_STUCK in round 6 records)
 *   ATTEMPT_RECORDED   problem_id, version, entry, read_as, reading_note, verdict, normalized, signatures, points_to, slip, response_sha256, scope
 *   PROBLEM_CLOSED     problem_id, version, reason (ANSWERED | SKIPPED | NOT_LEARNED | SET_ASIDE | SESSION_ENDED)
 *   CHECK_HELD         problem_id, version, reason (HELP_BEFORE_CHECK | HELP_DURING_CHECK): written in round 8 records only; round 9
 *                      §3.2 makes every text detector a logged signal. Attempts on such a problem stay held.
 *   ATTEMPT_HELD       attempt_id, problem_id, reason (VERDICT_CONFLICT): that attempt is held
 *
 *   PROBLEM_WAITING    problem_id, version: a check with help waits as the original problem (round 9 §4.3)
 *   CORRECTION         attempt_id, field (help | verdict), value, reason: appended by tools/correct_attempt.php (round 9 §4.7)
 *
 * A held attempt keeps its verdict but counts neither for nor against a goal
 * (round 7 §5). A problem presented again as the original (round 9 §4.3)
 * keeps the help already recorded on it. The latest correction of each field
 * of an attempt is applied; the original values stay in the attempt record
 * and every correction is listed with it. A verdict correction ends a hold
 * from a verdict conflict (owner's decision on round 9 §4.7).
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
        $corrections = [];
        foreach ($events as $e) {
            if ($e['type'] === 'CHECK_HELD') {
                $heldProblems[$e['data']['problem_id']] ??= $e['data']['reason'];
            } elseif ($e['type'] === 'ATTEMPT_HELD') {
                $heldAttempts[$e['data']['attempt_id']] ??= $e['data']['reason'];
            } elseif ($e['type'] === 'CORRECTION') {
                $corrections[$e['data']['attempt_id']][] = $e['data'] + ['event_id' => $e['event_id'], 'at' => $e['created_at'] ?? null];
            }
        }
        foreach ($events as $e) {
            $d = $e['data'];
            switch ($e['type']) {
                case 'PROBLEM_PRESENTED':
                    $problems[$d['problem_id']] = $d;
                    $help[$d['problem_id']] ??= [];
                    break;
                case 'HELP_GIVEN':
                    $help[$d['problem_id']][] = $d['choice'];
                    break;
                case 'ATTEMPT_RECORDED':
                    $p = $problems[$d['problem_id']] ?? null;
                    if ($p === null) {
                        break; // an attempt must belong to a presented problem
                    }
                    $attempt = [
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
                    $out[] = self::corrected($attempt, $corrections[$e['event_id']] ?? []);
                    break;
            }
        }
        return $out;
    }

    /**
     * Round 9 §4.7: apply the latest correction of each field. The original
     * values are kept in 'original', and every correction in 'corrections'.
     *
     * @param list<array> $list corrections for this attempt, in ledger order
     */
    private static function corrected(array $a, array $list): array
    {
        $a['corrections'] = $list;
        if ($list === []) {
            return $a;
        }
        $a['original'] = ['verdict' => $a['verdict'], 'help' => $a['help'], 'held' => $a['held']];
        $latest = [];
        foreach ($list as $c) {
            $latest[$c['field']] = $c['value'];
        }
        if (isset($latest['help'])) {
            $a['help'] = $latest['help'] === 'with_help' ? array_values(array_unique(array_merge($a['help'], ['CORRECTION']))) : [];
        }
        if (isset($latest['verdict'])) {
            $a['verdict'] = $latest['verdict'];
            if ($a['held'] === 'VERDICT_CONFLICT') {
                $a['held'] = null;
            }
        }
        return $a;
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
