<?php
declare(strict_types=1);

namespace AlgebraEngine\Report;

use AlgebraEngine\Curriculum\BetaScope;
use AlgebraEngine\Curriculum\CurriculumRepository;

/**
 * Round UI (U2): the two levels of the session summary page, in plain words.
 * The learner level names skills by title only; the teacher level holds the
 * full record with skill IDs in brackets after titles. Both are built from
 * the same accepted ledger events and computed results as the report; the
 * report record itself (and its stored codes) is unchanged.
 */
final class SummaryView
{
    /**
     * Plain words for record text: the summary never shows these terms
     * (declared prerequisite, descent, implicated, branch, transfer variant,
     * retention claim, faded support, coverage) or raw codes.
     */
    private const WORDS = [
        '/\bRepair goal met \(3 correct in a row without help\) at [A-Z]\d{2}\./' => 'the goal there was met (3 correct in a row without help).',
        '/\bParent ([A-Z]\d{2}) re-verified with fresh work\./' => '$1 was checked again with fresh questions.',
        '/\bPrerequisite check succeeded; repair at the parent\./' => 'the building block was answered correctly, so step-by-step work moved to the skill above it.',
        '/\bPrerequisite ([A-Z]\d{2}) succeeded independently; bounded instructional trial at ([A-Z]\d{2})\./' => 'the building block $1 was answered correctly without help, so a short round of step-by-step questions followed at $2.',
        '/\bImplicated prerequisite ([A-Z]\d{2}) is not available in beta; repair at ([A-Z]\d{2})\./' => 'the error pattern pointed to $1, which is not available in beta, so the step-by-step work stayed at $2.',
        '/\bError not reproduced on the probe; bounded instructional trial before further independent checks\./' => 'the error did not happen again on a fresh question, so a short round of step-by-step questions came before more questions without help.',
        '/\bNo discriminating branch; bounded instructional trial at ([A-Z]\d{2})\./' => 'no error pattern pointed to one building block, so a short round of step-by-step questions followed at $1.',
        '/\bDescent bounded; repair at ([A-Z]\d{2})\./' => 'moving down to building blocks reached its limit, so the step-by-step work stayed at $1.',
        '/\bProbe budget reached without usable evidence; bounded instructional trial\./' => 'the extra questions ran out without a clear result, so a short round of step-by-step questions followed.',
        '/\bLearner reports not having learned this; bounded instructional trial \(not counted as incorrect\)\./' => 'the learner said they had not learned this yet, so a short round of step-by-step questions followed (not counted as incorrect).',
        '/\bCheck at ([A-Z]\d{2}) was answered with help or not in the requested form; bounded instructional trial\./' => 'the check at $1 was answered with help or not in the requested form, so a short round of step-by-step questions followed.',
        '/\bAn independent-check attempt answered with help was not correct; continue guided work \(not a failed cycle\)\./' => 'an answer on the check, given after help, was not correct, so step-by-step work continued (not a failed round).',
        '/\bFading support: whole problem first; smaller questions only if needed\./' => 'the whole problem first, smaller questions only if needed.',
        '/ A new guided problem with different numbers\./' => ' A new problem with different numbers.',
        '/\bThree independent-check cycles at this repair point did not meet the goal after intervening instruction\. Observations are retained; no foundational incapacity is inferred\./' => 'Three rounds of checks here did not reach the goal after step-by-step work. What was seen is kept; nothing is concluded about ability.',
        '/\bModel provider: .*?\. Accepted work is preserved; this is not a learner failure\./' => 'The tutor model did not give a usable reply. Saved work is kept; this is not a learner failure.',
        '/\bThe canonical record for ([A-Z]\d{2}) is not available\./' => 'The curriculum record for $1 is not available.',
        '/\bbounded instructional trial\b/' => 'short round of step-by-step questions',
        '/\b[Dd]eclared prerequisites\b/' => 'listed building blocks',
        '/\b[Dd]eclared prerequisite\b/' => 'listed building block',
        '/\bprerequisites\b/' => 'building blocks',
        '/\bprerequisite\b/' => 'building block',
        '/\bPrerequisite\b/' => 'Building block',
        '/\b[Ii]mplicated\b/' => 'pointed to',
        '/\bbranch\b/' => 'possible cause',
        '/\bDescended\b/' => 'Moved down',
        '/\bdescended\b/' => 'moved down',
        '/\bDescent\b/' => 'Moving down',
        '/\bdescent\b/' => 'moving down',
        '/\b[Tt]ransfer variants?\b/' => 'new kind of question',
        '/\bretention claims?\b/' => 'claim about remembering later',
        '/\bretention\b/' => 'remembering later',
        '/\bfaded support\b/' => 'less help',
        '/\b[Cc]overage\b/' => 'what is checked',
        '/\s*\((?:all_of|any_of)\s+edge\)/' => '',
        '/\s*\((?:AVAILABLE_SCORED|PARTIAL_COMPONENTS|UNAVAILABLE)\)/' => '',
        '/\s*\(POLICY-[A-Z0-9-]+\)/' => '',
    ];

    public function __construct(private CurriculumRepository $curriculum)
    {
    }

    /**
     * @param array $built   the report payload from ReportBuilder::build() without 'summary'
     * @param list<array{event_id:string,type:string,data:array}> $events
     */
    public function build(array $built, array $events, string $provider): array
    {
        $report = $built['report'];
        $perSkill = $built['per_skill'];
        $targets = $report['targets_attempted'];
        $met = [];
        foreach ($report['immediate_criteria'] as $c) {
            $met[$c['skill_id']] = $c['component_id'] === 'three_variant_criterion' ? 'IMMEDIATE_CRITERION_MET' : 'COMPONENT_CRITERION_MET';
        }
        return [
            'learner' => $this->learner($perSkill, $targets, $met, $events),
            'teacher' => $this->teacher($built, $events, $met, $provider),
        ];
    }

    // ------------------------------------------------------------ learner

    private function learner(array $perSkill, array $targets, array $met, array $events): array
    {
        $workedOn = [];
        foreach ($perSkill as $s) {
            $workedOn[] = $this->title($s['skill_id']) . (in_array($s['skill_id'], $targets, true) ? ' (your topic)' : ' (a skill your topic builds on)');
        }
        $goals = [];
        foreach ($met as $skill => $status) {
            $goals[] = $this->title($skill) . ' — goal met: 3 correct in a row without help'
                . ($status === 'COMPONENT_CRITERION_MET' ? ' (the beta checks the numeric part of this skill only).' : ', including ' . BetaScope::kinds($skill) . '.');
        }
        $hard = [];
        foreach ($perSkill as $s) {
            $guided = count(array_filter($s['verdicts'], fn ($v) => $v['role'] === 'GUIDED'));
            $hinted = count(array_filter($s['verdicts'], fn ($v) => $v['role'] !== 'GUIDED' && !$v['independent']));
            if ($guided === 0 && $hinted === 0) {
                continue;
            }
            $how = array_filter([$hinted > 0 ? 'you answered after a hint or an explanation' : null, $guided > 0 ? 'you worked through it step by step with help' : null]);
            $hard[] = $this->title($s['skill_id']) . ': ' . implode(', and ', $how) . '.';
        }
        $next = [];
        foreach ($targets as $t) {
            $next[] = isset($met[$t])
                ? 'Try ' . $this->title($t) . ' again on another day to see whether it stays with you.'
                : 'Keep practising ' . $this->title($t) . '. The goal is 3 correct in a row without help' . (BetaScope::threeVariant($t) ? ', including ' . BetaScope::kinds($t) : '') . '.';
        }
        // Building blocks not yet met: where help was needed first, then the deepest; three lines at most in all.
        $blocks = [];
        foreach (array_reverse($perSkill) as $s) {
            $id = $s['skill_id'];
            if (!in_array($id, $targets, true) && !isset($met[$id]) && ($s['assisted'] > 0 || in_array('INCORRECT', array_column($s['verdicts'], 'verdict'), true))) {
                $blocks[$s['assisted'] > 0 ? 0 : 1][] = 'Practise ' . $this->title($id) . ', a skill your topic builds on.';
            }
        }
        ksort($blocks);
        $next = array_slice(array_merge($next, ...array_values($blocks)), 0, max(3, count($next)));
        return [
            'worked_on' => self::unique($workedOn),
            'goals_met' => self::unique($goals),
            'hard' => self::unique($hard),
            'next' => self::unique($next),
            'note' => 'These results describe this session only. They are not a grade and not a long-term judgement.',
        ];
    }

    // ------------------------------------------------------------ teacher

    private function teacher(array $built, array $events, array $met, string $provider): array
    {
        $report = $built['report'];
        $skills = [];
        foreach ($built['per_skill'] as $s) {
            $observed = [];
            foreach ($s['error_signatures_observed'] as $o) {
                $line = $this->text($o);
                $observed[$line] = ($observed[$line] ?? 0) + 1;
            }
            $skills[] = [
                'name' => $this->title($s['skill_id']) . ' (' . $s['skill_id'] . ')',
                'items_attempted' => $s['items_attempted'],
                'independent' => $s['independent'],
                'assisted' => $s['assisted'],
                'goal' => match ($s['criterion_status']) {
                    'IMMEDIATE_CRITERION_MET' => 'goal met: 3 correct in a row without help',
                    'COMPONENT_CRITERION_MET' => 'goal met: 3 correct in a row without help (numeric part only)',
                    default => 'not met yet',
                },
                'checked' => BetaScope::threeVariant($s['skill_id']) ? 'all three kinds of question' : 'the numeric part only',
                'observed' => array_map(fn ($line, $n) => $line . ($n > 1 ? ' Seen ' . $n . ' times.' : ''), array_keys($observed), array_values($observed)),
                'route' => self::unique(array_map(fn ($p) => $this->text(preg_replace(['/^to /', '/^from /'], ['moved down to ', 'came from '], $p)), $s['descent_path'])),
                'notes' => self::unique(array_map(fn ($n) => $this->text(str_replace(
                    ['(branch unresolved)', 'stuck at the smallest guided question; no declared prerequisite to check (unresolved)'],
                    ['(still open)', 'stuck at the smallest step-by-step question; no listed building block to check (still open)'], $n)), $s['boundary_notes'])),
            ];
        }

        $goals = [];
        foreach ($met as $skill => $status) {
            $goals[] = $skill . ' — goal met: 3 correct in a row without help'
                . ($status === 'COMPONENT_CRITERION_MET'
                    ? ', on the numeric part of the skill only (other parts are not checked in the beta).'
                    : ', including ' . BetaScope::kinds($skill) . '.')
                . ' A check from this session only; no long-term learning claim.';
        }
        $transfer = array_map(fn ($t) => 'New kind of question answered correctly without help: ' . preg_replace('/^Transfer variant answered correctly without help: /', '', $t['statement']), $report['transfer_evidence']);
        $guided = array_map(fn ($g) => $g['skill_id'] . ': ' . count($g['event_ids']) . ' step-by-step question(s) answered with help. Work with help does not count toward any goal.', $report['guided_work']);

        $repairs = [];
        $open = [];
        foreach ($events as $e) {
            $d = $e['data'];
            switch ($e['type']) {
                case 'DESCENT':
                    $repairs[] = 'Moved down from ' . $d['from_skill'] . ' to ' . $d['to_skill'] . ', a skill it builds on, following the error pattern the app matched (a routing choice, not a diagnosis of cause).';
                    break;
                case 'RETURN':
                    $repairs[] = 'Returned from ' . $d['from_skill'] . ' to ' . $d['to_skill'] . ': ' . ReportBuilder::plain($d['reason']);
                    break;
                case 'REPAIR_STARTED':
                    $repairs[] = 'Step-by-step questions at ' . $d['skill_id'] . ($d['faded'] ? ', with less help' : '') . ': ' . ReportBuilder::plain($d['reason']);
                    break;
                case 'BOUNDARY':
                    $open[] = match ($d['reason']) {
                        'NOT_AVAILABLE_IN_BETA' => 'The error pattern at ' . $d['from_skill'] . ' pointed to ' . $d['skill_id'] . ', which is not available in beta. That question stays open; it is not a learner failure.',
                        'NOT_A_DECLARED_PREREQUISITE' => 'At ' . $d['from_skill'] . ', the learner was stuck at the smallest step-by-step question, which practises '
                            . ($d['skill_id'] === $d['from_skill'] ? 'that skill itself' : $d['skill_id']) . '; there was no listed building block to check. That stays open; it is not a learner failure.',
                        'NO_IMPLICATED_BRANCH' => 'At ' . $d['from_skill'] . ', no error pattern pointed to one building block; the cause stays open.',
                        'DEPTH_LIMIT' => 'Moving down from ' . $d['from_skill'] . ' stopped: the limit on how far to go down was reached.',
                        'ALREADY_ON_PATH' => 'Moving down from ' . $d['from_skill'] . ' stopped: ' . $d['skill_id'] . ' was already on the path.',
                        default => 'Moving down from ' . $d['from_skill'] . ' stopped.',
                    };
                    break;
                case 'EPISODE_SUSPENDED':
                    $open[] = 'Work on ' . $d['original_target_skill_id'] . ' was interrupted by a topic change while at ' . $d['current_skill_id'] . ', with '
                        . count($d['return_stack']) . ' step(s) back still to do. Interrupted work is not a failure.';
                    break;
                case 'ISSUE':
                    $open[] = 'A system problem: ' . ReportBuilder::plain($d['detail']);
                    break;
                case 'PENDING_INPUT_UNSCORED':
                    $open[] = 'An answer sent while the tutor was still preparing the next step has no result and is not scored.';
                    break;
            }
        }
        foreach ($report['unresolved_or_untested'] as $u) {
            if (str_starts_with($u, 'A goal recorded as met for ')) {
                $open[] = $u;
            }
        }
        foreach ($built['per_skill'] as $s) {
            if (!BetaScope::threeVariant($s['skill_id'])) {
                $open[] = $s['skill_id'] . ': the beta checks only the numeric part of this skill; its other parts are not checked yet.';
            }
        }
        $open[] = 'Skills not listed above were not tested in this session and carry no pass or fail label.';
        $open[] = 'The beta has no later review check, so nothing here shows what is remembered over time.';

        $next = [];
        foreach ($report['targets_attempted'] as $t) {
            $next[] = isset($met[$t])
                ? 'A later independent review of ' . $t . ' would be needed to say whether it is remembered over time (the beta has no review checks yet).'
                : 'Fresh work on ' . $t . ' without help' . (BetaScope::threeVariant($t) ? ', including ' . BetaScope::kinds($t) : '') . ', to meet the goal: 3 correct in a row without help.';
        }

        $about = 'Goal: 3 correct in a row without help in one session. For ' . $this->title('Q07') . ' (Q07) and ' . $this->title('E06') . ' (E06) the three must include three kinds of question. '
            . 'An answer given after a hint starts the count again. This is a check from this session only, not a long-term learning claim. '
            . ($provider === 'mock'
                ? 'Simulated tutor (mock mode): the wording came from a simulated model, so this is not evidence of how a live model behaves or of learning.'
                : 'Live provider: ' . $provider . '.');

        $section = fn (string $heading, array $lines, string $empty) => ['heading' => $heading, 'lines' => self::unique(array_map(fn ($l) => $this->text($l), $lines)), 'empty' => $empty];
        return [
            'status' => match ($report['status']) {
                'COMPLETED' => 'Completed',
                'INTERRUPTED' => 'Interrupted',
                'BOUNDARY_REACHED' => 'Stopped at the edge of this beta',
                default => 'In progress',
            },
            'topics' => array_map(fn ($t) => $this->title($t) . ' (' . $t . ')', $report['targets_attempted']),
            'skills' => $skills,
            'sections' => [
                $section('Goals met', $goals, 'No goal was met in this session.'),
                $section('New kinds of question answered without help', $transfer, 'No new kind of question was answered correctly without help.'),
                $section('Work with help', $guided, 'No step-by-step work in this session.'),
                $section('Step-by-step work and returns', $repairs, 'No step-by-step work was needed.'),
                $section('Route taken', $built['descent_path'], 'The session stayed on the topic.'),
                $section('Still open or not checked', $open, 'Nothing listed.'),
                $section('What would show more', $next, 'Nothing listed.'),
                $section('About these results', [$about], ''),
            ],
        ];
    }

    // ------------------------------------------------------------ text

    /** Plain words for record text, then "title (ID)" for every skill ID. */
    public function text(string $line): string
    {
        $line = (string) preg_replace(array_keys(self::WORDS), array_values(self::WORDS), ReportBuilder::plain($line));
        return (string) preg_replace_callback('/(?<![\w(])([A-Z]\d{2})(?![\w)])/', fn ($m) => $this->curriculum->has($m[1]) ? $this->title($m[1]) . ' (' . $m[1] . ')' : $m[1], $line);
    }

    private function title(string $skillId): string
    {
        return $this->curriculum->has($skillId) ? $this->curriculum->title($skillId) : $skillId;
    }

    /** @param list<string> $lines @return list<string> */
    private static function unique(array $lines): array
    {
        return array_values(array_unique(array_filter($lines, fn ($l) => trim($l) !== '')));
    }
}
