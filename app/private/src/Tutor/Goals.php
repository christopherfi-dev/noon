<?php
declare(strict_types=1);

namespace AlgebraEngine\Tutor;

use AlgebraEngine\Curriculum\BetaScope;

/**
 * Round 6 §5.4–§5.5: goals, computed by code alone from the gradebook. The
 * AI sees this status and plans with it; nothing it returns can mark a goal.
 *
 * Per skill: 3 check problems correct in a row, without help. For Q07 and
 * E06 the three must include a solve or expansion, a choice question and a
 * new form. Only check attempts count:
 * - correct with no help between presentation and answer: the run grows;
 * - incorrect: the run resets;
 * - help on a check (a hint, a help move, "I'm stuck"): the run restarts
 *   when the help is recorded, and that check's later answer counts neither
 *   for nor against the goal (round 10 §4.4, from the HELP_GIVEN events);
 *   only the first help on a check restarts it, since the check is practice
 *   with help after that. Help known only from a correction restarts the run
 *   at the answer, as before (round 5.1);
 * - a code-confirmed slip: the first in a run pauses the count (the slipped
 *   answer and its retry don't count, the run is not reset); a second slip
 *   in the same run resets it;
 * - partially correct (right value, not the form asked): neither;
 * - practice and retry attempts, skipped or not-learned problems: neither;
 * - a held attempt (a result under review; round 7 §5): neither; the run
 *   neither advances nor resets; help recorded on it restarted the run when
 *   the help was recorded (round 10 §4.4);
 * - a corrected attempt counts with its corrected values (round 9 §4.7).
 * A goal met in this session stays met in this session. Goals are an
 * in-session criterion only; nothing here is a mastery claim.
 */
final class Goals
{
    public const REQUIRED = 3;
    public const WORDING = '3 correct without help (one slip may pause the count)';

    /** Round 10 §4.4: goals from a ledger, with help taken into account when it was recorded. */
    public static function fromEvents(array $events): array
    {
        return self::compute(Gradebook::attempts($events), Gradebook::checkHelp($events));
    }

    /**
     * @param list<array<string,mixed>> $attempts Gradebook::attempts()
     * @param list<array{seq:int,problem_id:string,skill_id:string}>|null $checkHelp Gradebook::checkHelp(); null: help counts at the answer only
     * @return array<string,array{skill_id:string,count:int,required:int,met:bool,met_attempt_ids:list<string>,covered:list<string>,
     *   still_needed:list<string>,pause_used:bool,paused:bool,run_attempt_ids:list<string>,three_variant:bool}>
     *   paused: the last check attempt was a slip that paused the count
     */
    public static function compute(array $attempts, ?array $checkHelp = null): array
    {
        $state = [];
        $timeline = [];
        foreach ($attempts as $i => $a) {
            $timeline[] = [$a['seq'] ?? $i, 1, $a];
        }
        foreach ($checkHelp ?? [] as $h) {
            $timeline[] = [$h['seq'], 0, $h];
        }
        usort($timeline, fn ($x, $y) => [$x[0], $x[1]] <=> [$y[0], $y[1]]);
        $restarted = [];
        foreach ($timeline as [, $isAttempt, $a]) {
            if (!$isAttempt) {
                // Round 10 §4.4: the first help on an unanswered check restarts that skill's run now.
                if (!isset($restarted[$a['problem_id']])) {
                    $restarted[$a['problem_id']] = true;
                    $state[$a['skill_id']] ??= self::fresh($a['skill_id']);
                    self::reset($state[$a['skill_id']]);
                    $state[$a['skill_id']]['paused'] = false;
                }
                continue;
            }
            $helped = $a['help'] !== [];
            // A check whose help already restarted the run counts neither for nor against; so does a held check without help.
            if ($a['purpose'] !== 'CHECK' || isset($restarted[$a['problem_id']]) || (($a['held'] ?? null) !== null && !$helped)) {
                continue;
            }
            $s = $a['skill_id'];
            $state[$s] ??= self::fresh($s);
            $g = &$state[$s];
            $g['paused'] = false;
            if ($helped && in_array($a['verdict'], ['CORRECT', 'INCORRECT', 'PARTIAL'], true)) {
                self::reset($g);
            } elseif ($a['verdict'] === 'CORRECT') {
                $g['run'][] = ['id' => $a['attempt_id'], 'variant' => $a['variant_id'] ?? 'constructed'];
                if (!$g['met']) {
                    $window = self::window($s, $g['run']);
                    if ($window !== null) {
                        $g['met'] = true;
                        $g['met_attempt_ids'] = $window;
                    }
                }
            } elseif ($a['verdict'] === 'INCORRECT') {
                if ($a['slip'] !== null && !$g['pause_used']) {
                    $g['pause_used'] = true; // the count pauses; the run is kept
                    $g['paused'] = true;
                } else {
                    self::reset($g);
                }
            }
            unset($g);
        }
        $out = [];
        foreach ($state as $s => $g) {
            $out[$s] = self::view($s, $g);
        }
        return $out;
    }

    /** Status for one skill, including skills with no check attempts yet. */
    public static function forSkill(array $computed, string $skill): array
    {
        return $computed[$skill] ?? self::view($skill, self::fresh($skill));
    }

    private static function fresh(string $skill): array
    {
        return ['run' => [], 'met' => false, 'met_attempt_ids' => [], 'pause_used' => false, 'paused' => false];
    }

    private static function reset(array &$g): void
    {
        $g['run'] = [];
        $g['pause_used'] = false;
    }

    private static function view(string $skill, array $g): array
    {
        $three = BetaScope::threeVariant($skill);
        $recent = array_column(array_slice($g['run'], -self::REQUIRED), 'variant');
        $covered = array_values(array_unique($recent));
        return [
            'skill_id' => $skill,
            'count' => min(count($g['run']), self::REQUIRED),
            'required' => self::REQUIRED,
            'met' => $g['met'],
            'met_attempt_ids' => $g['met_attempt_ids'],
            'covered' => $covered,
            'still_needed' => $three && !$g['met'] ? array_values(array_diff(BetaScope::VARIANTS, $covered)) : [],
            'pause_used' => $g['pause_used'],
            'paused' => $g['paused'],
            'run_attempt_ids' => array_column($g['run'], 'id'),
            'three_variant' => $three,
        ];
    }

    /** @param list<array{id:string,variant:string}> $run @return list<string>|null */
    private static function window(string $skill, array $run): ?array
    {
        for ($i = 0; $i + self::REQUIRED <= count($run); $i++) {
            $w = array_slice($run, $i, self::REQUIRED);
            if (!BetaScope::threeVariant($skill) || count(array_intersect(BetaScope::VARIANTS, array_unique(array_column($w, 'variant')))) === 3) {
                return array_column($w, 'id');
            }
        }
        return null;
    }
}
