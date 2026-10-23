<?php
declare(strict_types=1);

namespace AlgebraEngine\Progress;

use AlgebraEngine\Curriculum\BetaScope;
use AlgebraEngine\Tutor\Gradebook;

/**
 * Round UI v3 (U10), adapted in round 6 (§8): what a progress record holds,
 * derived from the gradebook only. Per skill: a status, the date and the
 * observed error-signature codes; plus the route target, the AI's stated next
 * focus and the next skill. No answers, names or addresses.
 */
final class ProgressRecord
{
    public const GOAL_MET = 'GOAL_MET';
    public const NEEDED_HELP = 'NEEDED_HELP';
    public const UNRESOLVED = 'UNRESOLVED';
    public const NOT_IN_BETA = 'NOT_IN_BETA';
    private const MAX_PATTERNS = 10;

    /**
     * This session's result for each skill it touched.
     *
     * @param list<array{type:string,data:array}> $events the session's accepted ledger
     * @param array<string,array{met:bool}> $goals Goals::compute() of the same ledger
     * @return array<string,array{status:string,date:string,patterns:list<string>}>
     */
    public static function sessionSkills(array $events, array $goals, string $date): array
    {
        $touched = [];
        $help = [];
        $patterns = [];
        foreach (Gradebook::attempts($events) as $a) {
            $s = $a['skill_id'];
            $touched[$s] = true;
            if ($a['help'] !== [] || ($a['purpose'] !== 'CHECK' && $a['verdict'] !== 'CORRECT')) {
                $help[$s] = true;
            }
            foreach ($a['signatures'] as $code) {
                $patterns[$s][$code] = true;
            }
        }
        foreach (Gradebook::problems($events) as $p) {
            $touched[$p['skill_id']] ??= true;
        }
        $out = [];
        foreach (array_keys($touched) as $skill) {
            $out[$skill] = [
                'status' => ($goals[$skill]['met'] ?? false) ? self::GOAL_MET : (isset($help[$skill]) ? self::NEEDED_HELP : self::UNRESOLVED),
                'date' => $date,
                'patterns' => array_slice(array_keys($patterns[$skill] ?? []), 0, self::MAX_PATTERNS),
            ];
        }
        return $out;
    }

    /** Cumulative: this session's results replace earlier ones for the skills it touched. */
    public static function merge(array $earlier, array $now): array
    {
        return array_replace($earlier, $now);
    }

    /**
     * Where the next session starts: the AI's stated next focus, then the
     * current focus, then the topic, then the route target — the first whose
     * goal is not met in this session. Null when every one is met.
     */
    public static function nextSkill(?string $nextFocus, ?string $focus, ?string $topic, ?string $route, array $goals): ?string
    {
        foreach ([$nextFocus, $focus, $topic, $route] as $skill) {
            if ($skill !== null && BetaScope::contains($skill) && !($goals[$skill]['met'] ?? false)) {
                return $skill;
            }
        }
        return null;
    }
}
