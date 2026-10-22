<?php
declare(strict_types=1);

namespace AlgebraEngine\Report;

use AlgebraEngine\Curriculum\BetaScope;
use AlgebraEngine\Curriculum\CurriculumRepository;
use AlgebraEngine\Evidence\CriteriaCalculator;
use AlgebraEngine\Provider\SchemaValidator;
use AlgebraEngine\Support\Ids;
use AlgebraEngine\Support\Time;

/**
 * Reconstructs the session report from accepted ledger events only (spec 06,
 * addendum §7). Observations, hypotheses and learning claims stay separate;
 * each claim lists its event IDs and checking scope. The report never says
 * a skill is mastered and labels simulated-provider sessions as mock.
 *
 * Round 5.1 ruling 4: the summary is learner-visible, so its prose uses plain
 * words (R5-B). Field names and stored data are unchanged; text copied from
 * stored records goes through plain().
 */
final class ReportBuilder
{
    public function __construct(private CurriculumRepository $curriculum, private SchemaValidator $schemas)
    {
    }

    public function build(array $row, array $events, string $status, string $provider, \DateTimeImmutable $now): array
    {
        $snap = $row['snapshot'];
        $computed = CriteriaCalculator::compute($events);
        $presented = [];
        $responses = [];
        $support = [];
        foreach ($events as $e) {
            if ($e['type'] === 'ITEM_PRESENTED') {
                $presented[$e['data']['item_id'] . '#' . $e['data']['item_version']] = $e['data'];
            } elseif ($e['type'] === 'RESPONSE_SUBMITTED') {
                $responses[$e['event_id']] = $e['data'];
            } elseif ($e['type'] === 'SUPPORT_GIVEN') {
                $support[$e['data']['item_id']][] = $e['event_id'];
            }
        }

        $perSkill = [];
        $touch = function (string $skill) use (&$perSkill) {
            $perSkill[$skill] ??= [
                'skill_id' => $skill, 'title' => $this->curriculum->title($skill), 'items_attempted' => 0, 'independent' => 0, 'assisted' => 0,
                'unscorable' => 0, 'verdicts' => [], 'error_signatures_observed' => [], 'descent_path' => [], 'criterion_status' => 'NOT_MET',
                'whole_skill_requirements_satisfied' => false, 'runtime_coverage' => BetaScope::threeVariant($skill) ? 'AVAILABLE_SCORED (three variants)' : 'PARTIAL_COMPONENTS (numeric component only)',
                'boundary_notes' => [],
            ];
        };
        $independentEvidence = [];
        $guided = [];
        $transfer = [];
        foreach ($events as $e) {
            if ($e['type'] !== 'CHECKER_RESULT') {
                continue;
            }
            $r = $e['data']['result'];
            $item = $presented[$r['item_id'] . '#' . $r['item_version']] ?? null;
            if ($item === null) {
                continue;
            }
            $skill = $item['skill_id'];
            $touch($skill);
            $p = &$perSkill[$skill];
            if ($r['verdict'] === 'UNSCORABLE') {
                $p['unscorable']++;
                unset($p);
                continue;
            }
            $p['items_attempted']++;
            $independent = $r['independent_eligible'] && !isset($support[$r['item_id']]) && !($item['model_text_shown'] ?? false);
            $independent ? $p['independent']++ : $p['assisted']++;
            $p['verdicts'][] = ['event_id' => $r['event_id'], 'role' => $item['role'], 'variant_id' => $item['variant_id'], 'verdict' => $r['verdict'],
                'independent' => $independent, 'checking_scope' => self::plain($r['verification_scope'])];
            unset($p);
            if ($item['role'] === 'GUIDED') {
                $guided[$skill][] = $r['event_id'];
            } elseif ($independent && $r['verdict'] === 'CORRECT' && $item['variant_id'] === 'transfer') {
                $transfer[] = ['statement' => 'Transfer variant answered correctly without help: ' . $item['prompt'], 'skill_id' => $skill,
                    'component_id' => $item['component_id'], 'event_ids' => [$r['event_id']], 'review_scope' => self::plain($r['verification_scope']) . ' Only this specific new form was tested.'];
            }
        }
        foreach ($computed['scored'] as $s) {
            if (!$s['eligible']) {
                continue;
            }
            $key = $s['skill_id'] . '|' . $s['verdict'];
            $independentEvidence[$key] ??= ['skill_id' => $s['skill_id'], 'verdict' => $s['verdict'], 'event_ids' => [], 'variants' => []];
            $independentEvidence[$key]['event_ids'][] = $s['event_id'];
            $independentEvidence[$key]['variants'][] = $s['variant_id'];
        }

        $hypotheses = [];
        $repairs = [];
        $unresolved = [];
        $criteria = [];
        $descents = [];
        foreach ($events as $e) {
            $d = $e['data'];
            switch ($e['type']) {
                case 'OBSERVATION':
                    $hypotheses[] = ['kind' => 'OBSERVATION', 'statement' => self::plain($d['statement']) . ($d['branch'] !== null ? ' (Observation only; not a confirmed cause.)' : ''),
                        'evidence_event_ids' => $d['evidence_event_ids'], 'hypothesis_status' => null];
                    if (isset($d['skill_id']) && $d['code'] !== null && !in_array($d['code'], ['ERROR_NOT_REPRODUCED', 'PREREQUISITE_SUCCEEDED', 'PARENT_REVERIFIED', 'PROBE_INCONCLUSIVE_AFTER_HELP', 'BREAKDOWN_CONFIRMED_AFTER_HELP'], true)) {
                        $touch($d['skill_id']);
                        $perSkill[$d['skill_id']]['error_signatures_observed'][] = self::plain($d['statement']) . ' (observation, not a confirmed cause)';
                    }
                    break;
                case 'HYPOTHESIS':
                    $hypotheses[] = ['kind' => 'HYPOTHESIS', 'statement' => self::plain($d['statement'] . (isset($d['note']) ? ' ' . $d['note'] : '')),
                        'evidence_event_ids' => $d['evidence_event_ids'], 'hypothesis_status' => $d['status']];
                    break;
                case 'DESCENT':
                    $descents[] = $d['from_skill'] . ' → ' . $d['to_skill'];
                    $repairs[] = ['statement' => 'Descended from ' . $d['from_skill'] . ' to declared prerequisite ' . $d['to_skill'] . ' (' . $d['edge_type'] . ' edge) along the branch the observed error implicated.',
                        'skill_id' => $d['to_skill'], 'component_id' => null, 'event_ids' => [$e['event_id'], $d['trigger_event_id']], 'review_scope' => 'Routing decision from error patterns the app matched; not a diagnosis of cause.'];
                    $touch($d['from_skill']);
                    $touch($d['to_skill']);
                    $perSkill[$d['from_skill']]['descent_path'][] = 'to ' . $d['to_skill'];
                    $perSkill[$d['to_skill']]['descent_path'][] = 'from ' . $d['from_skill'];
                    break;
                case 'RETURN':
                    $descents[] = $d['from_skill'] . ' ↩ ' . $d['to_skill'];
                    $repairs[] = ['statement' => 'Returned from ' . $d['from_skill'] . ' to ' . $d['to_skill'] . ': ' . self::plain($d['reason']),
                        'skill_id' => $d['to_skill'], 'component_id' => null, 'event_ids' => array_merge([$e['event_id']], $d['evidence_event_ids']), 'review_scope' => 'Parent retested with fresh work; prerequisite success does not pass the parent.'];
                    break;
                case 'REPAIR_STARTED':
                    $repairs[] = ['statement' => 'Guided repair (questions only) at ' . $d['skill_id'] . ($d['faded'] ? ' with faded support' : '') . ': ' . self::plain($d['reason']),
                        'skill_id' => $d['skill_id'], 'component_id' => null, 'event_ids' => [$e['event_id']], 'review_scope' => 'Practice with help.'];
                    break;
                case 'BOUNDARY':
                    if ($d['reason'] === 'NOT_AVAILABLE_IN_BETA') {
                        $unresolved[] = $d['skill_id'] . ' (' . $this->curriculum->title($d['skill_id']) . ') was implicated from ' . $d['from_skill'] . ' but is not available in beta. That branch is unresolved; it is not a learner failure.';
                        $touch($d['from_skill']);
                        $perSkill[$d['from_skill']]['boundary_notes'][] = $d['skill_id'] . ': not available in beta (branch unresolved)';
                    } elseif ($d['reason'] === 'NOT_A_DECLARED_PREREQUISITE') {
                        $unresolved[] = 'At ' . $d['from_skill'] . ', the learner was stuck at the smallest guided question, which exercises '
                            . ($d['skill_id'] === $d['from_skill'] ? $d['from_skill'] . ' itself' : $d['skill_id']) . '; there was no declared prerequisite to check. That breakdown is unresolved; it is not a learner failure.';
                        $touch($d['from_skill']);
                        $perSkill[$d['from_skill']]['boundary_notes'][] = 'stuck at the smallest guided question; no declared prerequisite to check (unresolved)';
                    } elseif ($d['reason'] === 'NO_IMPLICATED_BRANCH') {
                        $unresolved[] = 'At ' . $d['from_skill'] . ', no observed error pattern implicated a specific prerequisite; the cause remains unresolved.';
                    } else {
                        $unresolved[] = 'Descent from ' . $d['from_skill'] . ' was bounded (' . $d['reason'] . ').';
                    }
                    break;
                case 'CRITERION_MET':
                    $stillMet = (bool) array_filter($computed['met'], fn ($m) => $m['skill_id'] === $d['skill_id'] && $m['episode_id'] === $d['episode_id']);
                    if (!$stillMet) {
                        $unresolved[] = 'A goal recorded as met for ' . $d['skill_id'] . ' was withdrawn after a correction; the recomputed results no longer meet it.';
                        break;
                    }
                    $criteria[] = ['statement' => $d['skill_id'] . ': ' . self::goalMet($d['status']), 'skill_id' => $d['skill_id'],
                        'component_id' => $d['status'] === 'IMMEDIATE_CRITERION_MET' ? 'three_variant_criterion' : 'numeric_component', 'event_ids' => $d['event_ids'],
                        'review_scope' => 'In-session goal (POLICY-IMMEDIATE-3); no retention or long-term learning claim.'];
                    break;
                case 'EPISODE_SUSPENDED':
                    $unresolved[] = 'Work on ' . $d['original_target_skill_id'] . ' was interrupted by a topic change while at ' . $d['current_skill_id'] . ' (' . count($d['return_stack']) . ' pending return step(s)). Interrupted work is not a failure.';
                    break;
                case 'ISSUE':
                    $unresolved[] = 'System issue ' . $d['type'] . ': ' . self::plain($d['detail']);
                    break;
                case 'PENDING_INPUT_UNSCORED':
                    $unresolved[] = 'An answer submitted while the provider call was pending has no accepted verdict and is unscored.';
                    break;
            }
        }
        foreach ($computed['criteria'] as $c) {
            if (isset($perSkill[$c['skill_id']]) && $c['immediate_status'] !== 'NOT_MET') {
                $perSkill[$c['skill_id']]['criterion_status'] = $c['immediate_status'];
                $perSkill[$c['skill_id']]['whole_skill_requirements_satisfied'] = $c['whole_skill_requirements_satisfied'];
            }
        }
        foreach ($perSkill as $skill => $p) {
            if (!BetaScope::threeVariant($skill)) {
                $unresolved[] = $skill . ': only the numeric component is implemented in the beta; the conceptual and transfer requirements of this skill are untested (PARTIAL_COMPONENTS).';
            }
        }
        $unresolved[] = 'Skills not listed above were not tested in this session and carry no pass or fail label.';
        $unresolved[] = 'No later review check exists in the beta, so no retention evidence was collected.';

        $targets = [];
        foreach ($events as $e) {
            if ($e['type'] === 'EPISODE_STARTED') {
                $targets[] = $e['data']['target_skill_id'];
            }
        }
        $targets = array_values(array_unique($targets));

        $next = [];
        foreach ($targets as $t) {
            $met = false;
            foreach ($computed['criteria'] as $c) {
                if ($c['skill_id'] === $t && $c['immediate_status'] !== 'NOT_MET') {
                    $met = true;
                }
            }
            $next[] = $met
                ? 'A later independent review of ' . $t . ' would be needed before any retention claim (review checks are not implemented in the beta).'
                : 'Fresh ' . $t . ' work without help' . (BetaScope::threeVariant($t) ? ' covering a solve, a first-step choice and a new form' : '') . ' to meet the goal: 3 correct in a row without help.';
        }

        $report = [
            'report_id' => Ids::make('rep'),
            'session_id' => $snap['session_id'],
            'state_revision' => $snap['revision'],
            'architecture_version' => '1.2',
            'curriculum_version' => CurriculumRepository::CURRICULUM_VERSION,
            'status' => $status,
            'targets_attempted' => $targets,
            'independent_evidence' => array_map(fn ($g) => [
                'statement' => $g['skill_id'] . ': ' . count($g['event_ids']) . ' independent ' . strtolower($g['verdict']) . ' response(s) (variants: ' . implode(', ', array_unique($g['variants'])) . ').',
                'skill_id' => $g['skill_id'],
                'component_id' => BetaScope::threeVariant($g['skill_id']) ? 'three_variant_criterion' : 'numeric_component',
                'event_ids' => array_slice($g['event_ids'], 0, 100),
                'review_scope' => 'Each answer was compared exactly with the answer the app computed; reasoning was not assessed.',
            ], array_values($independentEvidence)),
            'guided_work' => array_map(fn ($skill, $ids) => [
                'statement' => $skill . ': ' . count($ids) . ' step-by-step question(s) answered during repair. This work was done with help and does not count toward any goal.',
                'skill_id' => $skill, 'component_id' => null, 'event_ids' => array_slice($ids, 0, 100), 'review_scope' => 'Practice with help.',
            ], array_keys($guided), array_values($guided)),
            'repairs_and_returns' => array_slice($repairs, 0, 100),
            'immediate_criteria' => $criteria,
            'transfer_evidence' => $transfer,
            'retention_evidence' => [],
            'hypotheses' => array_slice($hypotheses, 0, 100),
            'unresolved_or_untested' => array_slice(array_values(array_unique($unresolved)), 0, 100),
            'next_evidence_needed' => $next,
            'policy_note' => 'Goal: 3 correct in a row without help in one session (for Q07 and E06 they must include a solve, a first-step choice and a new form). An answer given after a hint starts the count again. This is a check from this session only, not a long-term learning claim. '
                . ($provider === 'mock' ? 'MOCK PROVIDER: wording came from a simulated model; this is not evidence of live-model behaviour or of learning effectiveness.' : 'Live provider: ' . $provider . '.'),
            'created_at' => Time::iso($now),
        ];
        $errors = $this->schemas->errors($report, 'report.schema.json');
        if ($errors !== []) {
            throw new \LogicException('Report failed report.schema.json: ' . implode('; ', $errors));
        }
        return [
            'report' => $report,
            'per_skill' => array_values($perSkill),
            'descent_path' => $descents,
            'provider' => $provider,
            'provider_label' => $provider === 'mock' ? 'MOCK (simulated model; no live provider was used)' : 'LIVE PROVIDER',
            'architecture' => ['version' => '1.2', 'sha256' => $snap['binding']['architecture_sha256'], 'binding_acknowledged' => $snap['binding']['acknowledged'],
                'binding_note' => 'An acknowledged binding is not evidence of enforcement; the app enforces the contract.'],
            'policy_version' => '1.0',
        ];
    }

    /** The learner-visible statement for a met goal (stored status codes are unchanged). */
    private static function goalMet(string $status): string
    {
        return $status === 'IMMEDIATE_CRITERION_MET'
            ? 'goal met: 3 correct in a row without help, covering a solve, a first-step choice and a new form. A check from this session only; no long-term learning claim.'
            : 'goal met: 3 correct in a row without help, on the numeric part of the skill only (other parts are not checked in the beta). A check from this session only; no long-term learning claim.';
    }

    /** Plain words for text copied from stored records into the learner-visible summary (R5-B). */
    public static function plain(string $text): string
    {
        static $map = [
            '/\b(?:a|the) host-derived key\b/i' => 'the answer the app computed',
            '/\bhost-derived key\b/i' => 'answer the app computed',
            '/\bhost-matched\b/' => 'app-matched',
            '/\bHost\b/' => 'App',
            '/\bhost\b/' => 'app',
            '/\bIMMEDIATE_CRITERION_MET\b/' => 'goal met: 3 correct in a row without help',
            '/\bCOMPONENT_CRITERION_MET\b/' => 'goal met: 3 correct in a row without help (numeric part only)',
            '/\b[Cc]riterion met\b/' => 'goal met (3 correct in a row without help)',
            '/\bImmediate criterion\b/' => 'Goal',
            '/\bimmediate criterion\b/' => 'goal',
            '/\bcriteria\b/' => 'goals',
            '/\bCriterion\b/' => 'Goal',
            '/\bcriterion\b/' => 'goal',
            '/\bwas assisted\b/' => 'was answered with help',
            '/\bAssisted independent-check attempt\b/' => 'An independent-check attempt answered with help',
            '/\bAssisted\b/' => 'With help',
            '/\bassisted\b/' => 'with help',
            '/\b([Gg])uided step\(s\)/' => 'step-by-step question(s)',
            '/\bGuided steps\b/' => 'Step-by-step questions',
            '/\bGuided step\b/' => 'Step-by-step question',
            '/\bguided steps\b/' => 'step-by-step questions',
            '/\bguided step\b/' => 'step-by-step question',
            '/\bverdicts\b/' => 'results',
            '/\bverdict\b/' => 'result',
            '/\bexpected (?:value|answer)\b/' => 'correct answer',
            '/\banswer key\b/' => 'correct answer',
            '/\bmatched the key\b/' => 'matched the correct answer',
        ];
        return (string) preg_replace(array_keys($map), array_values($map), $text);
    }
}
