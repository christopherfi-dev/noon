<?php
declare(strict_types=1);

namespace AlgebraEngine\Evidence;

use AlgebraEngine\Curriculum\BetaScope;

/**
 * Recomputes immediate criteria from the accepted ledger every time; counters
 * are never decremented in place. An answer given after a hint or explanation
 * resets the run like a wrong answer (owner rule, round 5.1). A slip candidate
 * (round 6) pauses the run instead: the slipped answer counts neither way unless
 * the slip was not confirmed or a wrong retry withdrew the pause; the engine
 * allows one pause per run. A checker result counts only when it is
 * bound to the exact response event, item version and response hash, the
 * item was presented in an independent role without model-written text, no
 * support was given before the response, the item was not a reused instance,
 * and nothing invalidated it.
 */
final class CriteriaCalculator
{
    public const COUNTED_ROLES = ['TARGET', 'INDEPENDENT', 'PREREQUISITE', 'TRANSFER'];
    public const REQUIRED = 3;

    /**
     * @param list<array{event_id:string,type:string,data:array}> $events
     * @return array{criteria:list<array<string,mixed>>,met:list<array<string,mixed>>,rejected_bindings:list<string>,scored:list<array<string,mixed>>,runs:array<string,list<string>>,pauses:array<string,int>}
     */
    public static function compute(array $events): array
    {
        $presented = [];
        $supportedBefore = [];
        $responses = [];
        $invalid = [];
        // Round 6 (§5): a slip candidate pauses the run unless it was not confirmed or a wrong retry withdrew the pause.
        $slips = [];
        $withdrawn = [];
        foreach ($events as $e) {
            if ($e['type'] === 'CORRECTION') {
                foreach ($e['data']['invalidated_event_ids'] as $id) {
                    $invalid[$id] = true;
                }
            } elseif ($e['type'] === 'SLIP_CANDIDATE') {
                $slips[$e['data']['response_event_id']] = true;
            } elseif (in_array($e['type'], ['SLIP_NOT_CONFIRMED', 'SLIP_VOIDED'], true)) {
                $withdrawn[$e['data']['response_event_id']] = true;
            }
        }
        $pauses = [];   // key => pauses in the current run
        $runs = [];      // key "episode|skill" => list of ['event_id','variant']
        $state = [];     // key => criteria entry
        $met = [];
        $rejected = [];
        $scored = [];
        foreach ($events as $e) {
            $d = $e['data'];
            switch ($e['type']) {
                case 'ITEM_PRESENTED':
                    $presented[$d['item_id'] . '#' . $d['item_version']] = $d;
                    break;
                case 'SUPPORT_GIVEN':
                    $supportedBefore[$d['item_id'] . '#' . $d['item_version']] = true;
                    break;
                case 'RESPONSE_SUBMITTED':
                    $responses[$e['event_id']] = $d;
                    break;
                case 'CHECKER_RESULT':
                    $r = $d['result'];
                    $resp = $responses[$r['event_id']] ?? null;
                    if ($resp === null || $resp['item_id'] !== $r['item_id'] || $resp['item_version'] !== $r['item_version'] || $resp['response_sha256'] !== $r['response_sha256']) {
                        $rejected[] = $r['checker_result_id'];
                        break;
                    }
                    $ikey = $r['item_id'] . '#' . $r['item_version'];
                    $item = $presented[$ikey] ?? null;
                    if ($item === null || !in_array($item['role'], self::COUNTED_ROLES, true)) {
                        break;
                    }
                    $key = $item['episode_id'] . '|' . $item['skill_id'];
                    $state[$key] ??= self::entry($item['skill_id'], $item['episode_id']);
                    $runs[$key] ??= [];
                    // Round 5 (B14b): an item shown with model-written text is never counted as work without help.
                    $invalidated = isset($invalid[$r['event_id']]) || isset($invalid[$e['event_id']]);
                    $eligible = $item['independent_eligible'] && $r['independent_eligible'] && !isset($supportedBefore[$ikey])
                        && !($item['model_text_shown'] ?? false) && !$invalidated;
                    $paused = isset($slips[$r['event_id']]) && !isset($withdrawn[$r['event_id']]) && !$invalidated;
                    $scored[] = ['skill_id' => $item['skill_id'], 'episode_id' => $item['episode_id'], 'event_id' => $r['event_id'], 'verdict' => $r['verdict'], 'eligible' => $eligible, 'variant_id' => $item['variant_id'], 'slip' => $paused];
                    if ($paused) {
                        $pauses[$key] = ($pauses[$key] ?? 0) + 1; // the slipped answer counts neither way; the run is not reset
                        break;
                    }
                    // Round 5.1 (owner rule, overriding the package's "assisted neither advances nor resets"):
                    // an answer given after a hint or explanation breaks the run, exactly as a wrong answer does.
                    $afterHelp = isset($supportedBefore[$ikey]) && !$invalidated && in_array($r['verdict'], ['CORRECT', 'INCORRECT', 'PARTIAL'], true);
                    if (!$afterHelp && (!$eligible || !in_array($r['verdict'], ['CORRECT', 'INCORRECT'], true))) {
                        break; // partial, unscorable, reused, shown with model text or invalidated: neither advances nor resets
                    }
                    if ($r['verdict'] === 'INCORRECT' || $afterHelp) {
                        $runs[$key] = [];
                        $pauses[$key] = 0;
                    } else {
                        $runs[$key][] = ['event_id' => $r['event_id'], 'variant' => $item['variant_id']];
                    }
                    $entry = &$state[$key];
                    $entry['independent_correct_streak'] = count($runs[$key]);
                    $entry['streak_event_ids'] = array_column($runs[$key], 'event_id');
                    $window = self::qualifyingWindow($item['skill_id'], $runs[$key]);
                    $entry['covered_variant_ids'] = array_values(array_unique(array_column(array_slice($runs[$key], -self::REQUIRED), 'variant')));
                    if ($window !== null && $entry['immediate_status'] === 'NOT_MET') {
                        $three = BetaScope::threeVariant($item['skill_id']);
                        $entry['immediate_status'] = $three ? 'IMMEDIATE_CRITERION_MET' : 'COMPONENT_CRITERION_MET';
                        $entry['whole_skill_requirements_satisfied'] = $three;
                        $met[] = ['skill_id' => $item['skill_id'], 'episode_id' => $item['episode_id'], 'status' => $entry['immediate_status'], 'event_ids' => $window, 'at_event_id' => $r['event_id']];
                    }
                    unset($entry);
                    break;
            }
        }
        $runVariants = [];
        foreach ($runs as $key => $run) {
            $runVariants[$key] = array_column($run, 'variant');
        }
        return ['criteria' => array_values($state), 'met' => $met, 'rejected_bindings' => $rejected, 'scored' => $scored, 'runs' => $runVariants, 'pauses' => $pauses];
    }

    private static function entry(string $skill, string $episode): array
    {
        return [
            'skill_id' => $skill,
            'component_id' => BetaScope::threeVariant($skill) ? 'three_variant_criterion' : 'numeric_component',
            'target_episode_id' => $episode,
            'independent_correct_streak' => 0,
            'streak_event_ids' => [],
            'covered_variant_ids' => [],
            'immediate_status' => 'NOT_MET',
            'whole_skill_requirements_satisfied' => false,
        ];
    }

    /**
     * Three consecutive correct independent items; for Q07/E06 the three must
     * together include constructed, contrast and transfer variants.
     *
     * @param list<array{event_id:string,variant:string}> $run
     * @return list<string>|null event ids of the qualifying window
     */
    private static function qualifyingWindow(string $skill, array $run): ?array
    {
        $n = count($run);
        for ($i = 0; $i + self::REQUIRED <= $n; $i++) {
            $w = array_slice($run, $i, self::REQUIRED);
            if (!BetaScope::threeVariant($skill)) {
                return array_column($w, 'event_id');
            }
            $variants = array_unique(array_column($w, 'variant'));
            if (count(array_intersect(BetaScope::VARIANTS, $variants)) === 3) {
                return array_column($w, 'event_id');
            }
        }
        return null;
    }

    /**
     * Next variant to present so the current run can complete its coverage:
     * the first canonical variant absent from the last two correct items.
     *
     * @param list<string> $runVariants variants of the current consecutive-correct run
     */
    public static function nextVariant(string $skill, array $runVariants): string
    {
        if (!BetaScope::threeVariant($skill)) {
            return 'constructed';
        }
        $recent = array_slice($runVariants, -(self::REQUIRED - 1));
        foreach (BetaScope::VARIANTS as $v) {
            if (!in_array($v, $recent, true)) {
                return $v;
            }
        }
        return 'constructed';
    }
}
