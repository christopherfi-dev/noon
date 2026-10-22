<?php
declare(strict_types=1);

namespace AlgebraEngine\Progress;

use AlgebraEngine\Curriculum\BetaScope;

/**
 * Round UI v3 (U10): what a progress record holds, derived from accepted
 * ledger events only. Per skill: a status, the date and the observed
 * mistake-pattern codes; plus the route target and the next skill. No
 * answers, names or addresses.
 */
final class ProgressRecord
{
    public const GOAL_MET = 'GOAL_MET';
    public const NEEDED_HELP = 'NEEDED_HELP';
    public const UNRESOLVED = 'UNRESOLVED';
    public const NOT_IN_BETA = 'NOT_IN_BETA';

    /** Observation codes that describe routing, not a mistake pattern. */
    private const NOT_PATTERNS = ['ERROR_NOT_REPRODUCED', 'PREREQUISITE_SUCCEEDED', 'PARENT_REVERIFIED', 'PROBE_INCONCLUSIVE_AFTER_HELP', 'BREAKDOWN_CONFIRMED_AFTER_HELP'];
    private const MAX_PATTERNS = 10;

    /**
     * This session's result for each skill it touched.
     *
     * @param list<array{type:string,data:array}> $events the session's accepted ledger
     * @param array{met:list<array{skill_id:string}>} $computed CriteriaCalculator::compute() of the same ledger
     * @return array<string,array{status:string,date:string,patterns:list<string>}>
     */
    public static function sessionSkills(array $events, array $computed, string $date): array
    {
        $met = array_flip(array_column($computed['met'], 'skill_id'));
        $itemSkill = [];
        $touched = [];
        $help = [];
        $patterns = [];
        $outside = [];
        foreach ($events as $e) {
            $d = $e['data'];
            switch ($e['type']) {
                case 'ITEM_PRESENTED':
                    $itemSkill[$d['item_id'] . '#' . $d['item_version']] = $d['skill_id'];
                    if ($d['role'] === 'GUIDED') {
                        $help[$d['skill_id']] = true;
                    }
                    break;
                case 'SUPPORT_GIVEN':
                    $skill = $itemSkill[$d['item_id'] . '#' . $d['item_version']] ?? null;
                    if ($skill !== null) {
                        $help[$skill] = true;
                    }
                    break;
                case 'CHECKER_RESULT':
                    $touched[$d['skill_id']] = true;
                    break;
                case 'DESCENT':
                    $touched[$d['from_skill']] = true;
                    $touched[$d['to_skill']] = true;
                    break;
                case 'OBSERVATION':
                    if (isset($d['skill_id'], $d['code']) && !in_array($d['code'], self::NOT_PATTERNS, true)) {
                        $touched[$d['skill_id']] = true;
                        $patterns[$d['skill_id']][$d['code']] = true;
                    }
                    break;
                case 'BOUNDARY':
                    if ($d['reason'] === 'NOT_AVAILABLE_IN_BETA') {
                        $outside[$d['skill_id']] = true;
                    }
                    break;
            }
        }
        $out = [];
        foreach (array_keys($touched) as $skill) {
            $out[$skill] = [
                'status' => isset($met[$skill]) ? self::GOAL_MET : (isset($help[$skill]) ? self::NEEDED_HELP : self::UNRESOLVED),
                'date' => $date,
                'patterns' => array_slice(array_keys($patterns[$skill] ?? []), 0, self::MAX_PATTERNS),
            ];
        }
        foreach (array_keys($outside) as $skill) {
            $out[$skill] ??= ['status' => self::NOT_IN_BETA, 'date' => $date, 'patterns' => []];
        }
        return $out;
    }

    /**
     * The record is cumulative: this session's results replace earlier ones for
     * the skills it touched; other skills keep their earlier entry and date.
     *
     * @param array<string,array> $earlier
     * @param array<string,array> $now
     * @return array<string,array>
     */
    public static function merge(array $earlier, array $now): array
    {
        return array_replace($earlier, $now);
    }

    /**
     * The deepest skill on the current route whose goal is not met in this
     * session: the skill being worked on, then each parent waiting on the
     * return stack, then the episode's target, then the route target. Null
     * when every goal on the route is met.
     */
    public static function nextSkill(array $snapshot, array $computed, ?string $routeTarget): ?string
    {
        $met = array_flip(array_column($computed['met'], 'skill_id'));
        $candidates = [$snapshot['current_skill_id'] ?? null];
        foreach (array_reverse($snapshot['return_stack'] ?? []) as $frame) {
            $candidates[] = $frame['parent_skill_id'];
        }
        $candidates[] = $snapshot['original_target_skill_id'] ?? null;
        $candidates[] = $routeTarget;
        foreach ($candidates as $skill) {
            if ($skill !== null && BetaScope::contains($skill) && !isset($met[$skill])) {
                return $skill;
            }
        }
        return null;
    }
}
