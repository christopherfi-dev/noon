<?php
declare(strict_types=1);

namespace AlgebraEngine\Report;

use AlgebraEngine\Curriculum\BetaScope;
use AlgebraEngine\Curriculum\CurriculumRepository;
use AlgebraEngine\Provider\SchemaValidator;
use AlgebraEngine\Support\Ids;
use AlgebraEngine\Support\Time;
use AlgebraEngine\Tutor\Goals;
use AlgebraEngine\Tutor\Gradebook;

/**
 * Round 6 §2.5: code writes the report from the gradebook. The UI v3 report
 * and its rules are kept: four learner headings in order with their fixed
 * empty lines, plain words, titles only for the learner and "title (ID)" for
 * teachers, earlier sessions under their own label, never merged, nothing
 * invented to fill a heading. No AI-written text appears in it: the audit
 * list names the phrases and the check question they preceded; the AI's text
 * itself stays in the ledger for review. Held attempts (round 7 §5) are
 * listed as held and counted neither as successes nor as failures.
 */
final class TutorReport
{
    private SummaryView $words;
    /** @var array<string,string> problem id => prompt, for the audit list */
    private array $problemPrompts = [];

    public function __construct(private CurriculumRepository $curriculum, private SchemaValidator $schemas)
    {
        $this->words = new SummaryView($curriculum);
    }

    public function build(array $row, array $events, string $status, string $provider, \DateTimeImmutable $now): array
    {
        $snap = $row['snapshot'];
        $attempts = Gradebook::attempts($events);
        $goals = Goals::compute($attempts);
        $topics = [];
        $moves = [];
        $audit = [];
        $help = [];
        $slips = [];
        $readings = [];
        $open = [];
        $problems = Gradebook::problems($events);
        $this->problemPrompts = array_map(fn ($p) => $p['prompt'], $problems);
        foreach ($events as $e) {
            $d = $e['data'];
            switch ($e['type']) {
                case 'SESSION_STARTED':
                    $topics[] = $d['topic_skill_id'];
                    break;
                case 'TOPIC_CHANGED':
                    $topics[] = $d['to'];
                    break;
                case 'AI_MOVE':
                    if ($d['target_skill_id'] !== null && $d['target_skill_id'] !== $d['from_focus']) {
                        $moves[] = ['from' => $d['from_focus'], 'to' => $d['target_skill_id'], 'event_id' => $e['event_id']];
                    }
                    break;
                case 'CHECK_CARD_AUDIT':
                    $audit[] = $d;
                    break;
                case 'HELP_GIVEN':
                    // Round 8 §3.1: help the student asked for ("I'm stuck"), and help the tutor's move gave during an open check.
                    $skill = $problems[$d['problem_id']]['skill_id'] ?? null;
                    if ($skill !== null) {
                        $by = ($d['source'] ?? null) === 'TUTOR_MOVE' ? 'tutor' : 'asked';
                        $help[$skill][$by] = ($help[$skill][$by] ?? 0) + 1;
                    }
                    break;
                case 'ISSUE':
                    $open[] = 'A system problem: ' . SummaryView::plain(preg_replace('/^Model provider: .*?\. /', 'The tutor model did not give a usable reply. ', $d['detail']) ?? $d['detail']);
                    break;
                case 'PENDING_INPUT_UNSCORED':
                    $open[] = 'An answer sent while the tutor was still preparing its reply has no result and is not recorded as an attempt.';
                    break;
                case 'FALLBACK_USED':
                    // Round 6 records only: round 7 holds the turn instead of a code fallback.
                    $open[] = 'Once the tutor model\'s reply failed its checks twice; the app showed a practice question instead.';
                    break;
            }
        }
        $topics = array_values(array_unique($topics));
        foreach ($attempts as $a) {
            if ($a['slip'] !== null) {
                $slips[] = $a;
            }
            if ($a['reading_note'] === 'read from the student\'s wording') {
                $readings[] = $a;
            }
        }

        $perSkill = [];
        $held = [];
        foreach ($attempts as $a) {
            $s = $a['skill_id'];
            $perSkill[$s] ??= ['skill_id' => $s, 'title' => $this->curriculum->title($s), 'items_attempted' => 0, 'independent' => 0, 'assisted' => 0,
                'check_correct' => 0, 'check_not_yet' => 0, 'practice' => 0, 'practice_correct' => 0, 'not_yet' => 0, 'observed' => [], 'slips' => 0, 'points_to' => [], 'held' => 0];
            $p = &$perSkill[$s];
            $p['items_attempted']++;
            if (($a['held'] ?? null) !== null) {
                // Round 7 §5: kept in the record with its verdict; neither a success nor a failure.
                $p['held']++;
                $held[] = $a;
                unset($p);
                continue;
            }
            $checkNoHelp = $a['purpose'] === 'CHECK' && $a['help'] === [];
            $checkNoHelp ? $p['independent']++ : $p['assisted']++;
            if ($checkNoHelp) {
                $a['verdict'] === 'CORRECT' ? $p['check_correct']++ : $p['check_not_yet']++;
            }
            if ($a['purpose'] !== 'CHECK') {
                $p['practice']++;
                $p['practice_correct'] += $a['verdict'] === 'CORRECT' ? 1 : 0;
            }
            if ($a['verdict'] !== 'CORRECT' && $a['purpose'] !== 'RETRY') {
                $p['not_yet']++;
            }
            foreach ($a['signatures'] as $code) {
                $p['observed'][] = $code;
            }
            foreach ($a['points_to'] ?? [] as $skill) {
                $p['points_to'][] = $skill;
            }
            $p['slips'] += $a['slip'] !== null ? 1 : 0;
            unset($p);
        }

        $learner = $this->learner($perSkill, $topics, $goals, $help, $moves);
        $teacher = $this->teacher($perSkill, $topics, $goals, $help, $moves, $audit, $slips, $readings, $open, $status, $provider, $row['engine']['progress']['earlier'] ?? [], $held,
            array_values(array_filter($attempts, fn ($a) => $a['corrections'] !== [])));
        $report = $this->record($snap, $attempts, $goals, $perSkill, $topics, $moves, $status, $provider, $now);
        return [
            'report' => $report,
            'per_skill' => array_values($perSkill),
            'provider' => $provider,
            'provider_label' => $provider === 'mock' ? 'MOCK (simulated tutor; no live provider was used)' : 'LIVE PROVIDER',
            'architecture' => ['version' => '1.7', 'sha256' => $snap['binding']['architecture_sha256'], 'binding_acknowledged' => $snap['binding']['acknowledged'],
                'binding_note' => 'An acknowledged binding is not evidence of enforcement; the app enforces the guarantees.'],
            'audit' => array_map(fn ($a) => ['problem_id' => $a['problem_id'], 'phrases' => $a['phrases']], $audit),
            'summary' => ['learner' => $learner + ['earlier' => $this->words->earlier($row['engine']['progress']['earlier'] ?? [], false)], 'teacher' => $teacher],
            'policy_version' => '1.7',
        ];
    }

    // ------------------------------------------------------------ learner

    private static function times(int $n): string
    {
        return $n === 1 ? 'once' : ($n === 2 ? 'twice' : $n . ' times');
    }

    private function learner(array $perSkill, array $topics, array $goals, array $help, array $moves): array
    {
        $didWell = [];
        $trouble = [];
        $workedOn = [];
        $patterns = [];
        foreach ($perSkill as $id => $s) {
            $title = $this->words->title($id);
            if ($goals[$id]['met'] ?? false) {
                $didWell[] = $title . ' — goal met: ' . Goals::WORDING . (BetaScope::threeVariant($id) ? ', including ' . BetaScope::kinds($id) : '') . '.';
            } elseif ($s['check_correct'] > 0) {
                $didWell[] = $title . ': ' . $s['check_correct'] . ' correct check ' . ($s['check_correct'] === 1 ? 'answer' : 'answers') . ' without help.';
            }
            $parts = [];
            if ($s['not_yet'] > 0) {
                $parts[] = $s['not_yet'] . ' ' . ($s['not_yet'] === 1 ? 'answer was' : 'answers were') . ' not right yet';
            }
            if (isset($help[$id])) {
                $parts[] = 'you used help';
            }
            if ($parts !== []) {
                $trouble[] = $title . ': ' . implode('; ', $parts) . '.';
            }
            if ($s['practice'] > 0) {
                $workedOn[] = 'We practised “' . $title . '” together (' . $s['practice'] . ' ' . ($s['practice'] === 1 ? 'question' : 'questions') . ', ' . $s['practice_correct'] . ' correct).';
            }
            if (isset($help[$id]['asked'])) {
                $workedOn[] = 'You asked for help ' . self::times($help[$id]['asked']) . ' on “' . $title . '”.';
            }
            if (isset($help[$id]['tutor'])) {
                $workedOn[] = 'The tutor helped you ' . self::times($help[$id]['tutor']) . ' with a check question on “' . $title . '”, so ' . ($help[$id]['tutor'] === 1 ? 'it' : 'those') . ' became practice.';
            }
            foreach ($s['observed'] as $code) {
                if (isset(SummaryView::PATTERNS[$code])) {
                    $patterns[SummaryView::PATTERNS[$code]] = ($patterns[SummaryView::PATTERNS[$code]] ?? 0) + 1;
                }
            }
        }
        foreach ($patterns as $what => $n) {
            $trouble[] = ($n === 1 ? 'Once' : ($n === 2 ? 'Twice' : 'Several times')) . ' you ' . $what . '.';
        }
        $route = $this->route($moves);
        if ($route !== null) {
            $workedOn[] = $route;
        }
        $next = [];
        foreach ($topics as $t) {
            if (!($goals[$t]['met'] ?? false)) {
                $next[] = $this->words->title($t) . ': aim for ' . Goals::WORDING . (BetaScope::threeVariant($t) ? ', including ' . BetaScope::kinds($t) : '') . '.';
            }
        }
        foreach (array_reverse($perSkill, true) as $id => $s) {
            if (!in_array($id, $topics, true) && !($goals[$id]['met'] ?? false) && ($s['not_yet'] > 0 || isset($help[$id])) && count($next) < 3) {
                $next[] = $this->words->title($id) . ' (a skill your topic builds on): aim for ' . Goals::WORDING . '.';
            }
        }
        // A skill outside the tutor is listed only while a goal that depends on it is unmet (UI v3.1 ruling).
        foreach ($perSkill as $id => $s) {
            foreach (array_unique($s['points_to']) as $outside) {
                if (!BetaScope::contains($outside) && $this->curriculum->has($outside) && !($goals[$id]['met'] ?? false)) {
                    $next[] = 'Practise with your teacher: ' . $this->words->title($outside) . ' (not in this tutor yet).';
                }
            }
        }
        return [
            'did_well' => SummaryView::unique($didWell),
            'trouble' => SummaryView::unique($trouble),
            'worked_on' => SummaryView::unique($workedOn),
            'next_time' => SummaryView::unique($next),
            'headings' => SummaryView::HEADINGS,
            'empty' => SummaryView::EMPTY,
            'note' => 'These results describe this session only. They are not a grade and not a long-term judgement.',
        ];
    }

    /** "We went back to “B”, then returned to “A”." from the tutor's route, in order. */
    private function route(array $moves): ?string
    {
        $steps = [];
        foreach ($moves as $m) {
            $verb = $this->isBelow($m['to'], $m['from']) ? 'went back to' : ($this->isBelow($m['from'], $m['to']) ? 'returned to' : 'moved on to');
            $steps[] = ($steps === [] ? 'We ' . $verb : 'then ' . str_replace('went back', 'back', $verb)) . ' “' . $this->words->title($m['to']) . '”';
        }
        return $steps === [] ? null : implode(', ', $steps) . '.';
    }

    /** True when $lower is a prerequisite of $upper, directly or through other skills. */
    private function isBelow(string $lower, string $upper, int $depth = 0): bool
    {
        if ($depth > 8 || !$this->curriculum->has($upper)) {
            return false;
        }
        foreach ($this->curriculum->declaredPrerequisites($upper) as $p) {
            if ($p === $lower || $this->isBelow($lower, $p, $depth + 1)) {
                return true;
            }
        }
        return false;
    }

    // ------------------------------------------------------------ teacher

    /** Round 7 §5: how a held attempt is listed. */
    public const HELD_WORDS = [
        'HELP_BEFORE_CHECK' => 'held: possible help before the check question',
        'HELP_DURING_CHECK' => 'held: possible help during the check question',
        'VERDICT_CONFLICT' => 'result under review',
    ];

    /** Round 9 §4.7: how a corrected value is named in the teacher view. */
    public const CORRECTION_WORDS = [
        'with_help' => 'with help', 'without_help' => 'without help',
        'CORRECT' => 'correct', 'INCORRECT' => 'incorrect', 'PARTIAL' => 'partly correct',
    ];

    /**
     * Round 9 §4.7 (owner's decision): one teacher line per correction, with
     * the problem, the field, the old and new value, the reason and the date.
     * The learner view shows only the corrected counts.
     *
     * @param list<array> $corrected attempts with at least one correction
     * @return list<string>
     */
    private function correctionLines(array $corrected): array
    {
        $lines = [];
        foreach ($corrected as $a) {
            foreach ($a['corrections'] as $c) {
                $word = fn ($v) => self::CORRECTION_WORDS[$v] ?? strtolower((string) $v);
                $lines[] = 'On “' . $a['prompt'] . '”, the recorded ' . ($c['field'] === 'help' ? 'help' : 'result') . ' was corrected from ' . $word($c['previous'])
                    . ' to ' . $word($c['value']) . ($c['at'] !== null ? ' on ' . substr((string) $c['at'], 0, 10) : '') . ': ' . rtrim(trim((string) $c['reason']), '.') . '.';
            }
        }
        return $lines;
    }

    private function teacher(array $perSkill, array $topics, array $goals, array $help, array $moves, array $audit, array $slips, array $readings, array $open, string $status, string $provider, array $earlier, array $held = [], array $corrected = []): array
    {
        $skills = [];
        foreach ($perSkill as $id => $s) {
            $observed = [];
            foreach ($s['observed'] as $code) {
                $line = isset(SummaryView::PATTERNS[$code]) ? 'The answer ' . preg_replace('/^were /', 'was ', SummaryView::PATTERNS[$code]) . ' (an observation, not a confirmed cause).' : null;
                if ($line !== null) {
                    $observed[$line] = ($observed[$line] ?? 0) + 1;
                }
            }
            $skills[] = [
                'name' => $this->words->title($id) . ' (' . $id . ')',
                'items_attempted' => $s['items_attempted'],
                'independent' => $s['independent'],
                'assisted' => $s['assisted'],
                'goal' => ($goals[$id]['met'] ?? false) ? 'goal met: ' . Goals::WORDING : 'not met yet',
                'checked' => BetaScope::threeVariant($id) ? 'all three kinds of question' : 'the numeric part only',
                'observed' => array_map(fn ($line, $n) => $line . ($n > 1 ? ' Seen ' . $n . ' times.' : ''), array_keys($observed), array_values($observed)),
                'route' => SummaryView::unique(array_merge(
                    array_map(fn ($m) => 'moved to ' . $this->words->title($m['to']) . ' (' . $m['to'] . ')', array_filter($moves, fn ($m) => $m['from'] === $id)),
                    array_map(fn ($m) => 'came from ' . $this->words->title($m['from']) . ' (' . $m['from'] . ')', array_filter($moves, fn ($m) => $m['to'] === $id)))),
                'notes' => array_values(array_filter([
                    $s['slips'] > 0 ? $s['slips'] . ' small ' . ($s['slips'] === 1 ? 'slip' : 'slips') . ' confirmed by the app (the structure was right; one calculation went wrong).' : null,
                    $s['held'] > 0 ? $s['held'] . ' check ' . ($s['held'] === 1 ? 'answer' : 'answers') . ' held: not counted as correct or as not yet.' : null,
                ])),
            ];
        }
        $goalLines = [];
        foreach ($goals as $id => $g) {
            if ($g['met']) {
                $goalLines[] = $id . ' — goal met: ' . Goals::WORDING . (BetaScope::threeVariant($id) ? ', including ' . BetaScope::kinds($id) : '') . '. A check from this session only; no long-term learning claim.';
            }
        }
        $practice = [];
        foreach ($perSkill as $id => $s) {
            if ($s['practice'] > 0 || isset($help[$id])) {
                $practice[] = $id . ': ' . $s['practice'] . ' practice ' . ($s['practice'] === 1 ? 'question' : 'questions') . ' with the tutor'
                    . (isset($help[$id]['asked']) ? '; help asked for ' . $help[$id]['asked'] . ' ' . ($help[$id]['asked'] === 1 ? 'time' : 'times') : '')
                    . (isset($help[$id]['tutor']) ? '; the tutor helped with an open check question ' . $help[$id]['tutor'] . ' ' . ($help[$id]['tutor'] === 1 ? 'time' : 'times') . ', so that check became practice' : '')
                    . '. Practice and answers after help do not count toward any goal.';
            }
        }
        $routeLines = array_map(fn ($m) => 'The tutor moved from ' . $m['from'] . ' to ' . $m['to'] . ($this->isBelow($m['to'], $m['from']) ? ', a skill it builds on' : '') . ' (the tutor\'s choice of route, not a diagnosis of cause).', $moves);
        $slipLines = array_map(fn ($a) => 'On ' . $a['prompt'] . ' the answer ' . $a['entry'] . ' had the right structure with one calculation error. '
            . ($a['purpose'] === 'CHECK' ? 'The count was paused, not reset; the answer and its retry did not count.' : 'It was a practice question.'), $slips);
        $readLines = array_map(fn ($a) => 'The entry “' . mb_substr($a['entry'], 0, 80) . '” was read from the student\'s wording as ' . $a['read_as'] . ' (' . ($a['purpose'] === 'CHECK' ? 'check question' : 'practice') . ').', $readings);
        $problems = $this->problemPrompts;
        $auditLines = [];
        foreach ($audit as $a) {
            $what = [];
            if (($a['phrases'] ?? []) !== []) {
                $what[] = 'phrases directed at it: ' . implode(', ', $a['phrases']);
            }
            if (($a['answer_in_text'] ?? false) && ($a['answer_source'] ?? null) === null) {
                $what[] = 'its answer';
            } elseif ($a['answer_in_text'] ?? false) {
                $what[] = 'a number equal to its answer that comes from ' . $a['answer_source'] . ' (a signal only)';
            }
            if (($a['shared_numbers'] ?? []) !== []) {
                $what[] = 'numbers it shares (' . implode(', ', $a['shared_numbers']) . '; a signal only)';
            }
            if ($what === []) {
                continue;
            }
            $auditLines[] = 'Before the check question “' . ($problems[$a['problem_id']] ?? 'a check question') . '”, the tutor\'s text had ' . implode('; ', $what)
                . (($a['held'] ?? false) ? '. The answer to it is held.' : '.') . ' (Audit list only; the tutor\'s own text is not part of this report.)';
        }
        $heldLines = array_map(fn ($a) => 'On “' . $a['prompt'] . '” the answer ' . $a['entry'] . ' was recorded as ' . strtolower($a['verdict']) . ', ' . (self::HELD_WORDS[$a['held']] ?? 'held')
            . '. It counts neither toward nor against the goal.', $held);
        foreach ($goals as $id => $g) {
            if ($g['met'] && !BetaScope::threeVariant($id)) {
                $open[] = $id . ': the goal covers the numeric part only; explaining a method or rejecting a wrong one was not checked.';
            }
        }
        $open[] = 'Skills not listed above were not tested in this session and carry no pass or fail label.';
        $open[] = 'There is no later review check yet, so nothing here shows what is remembered over time.';
        $next = [];
        foreach ($topics as $t) {
            $next[] = ($goals[$t]['met'] ?? false)
                ? 'A later independent review of ' . $t . ' would be needed to say whether it is remembered over time.'
                : 'Fresh check questions on ' . $t . ' without help' . (BetaScope::threeVariant($t) ? ', including ' . BetaScope::kinds($t) : '') . ', to meet the goal: ' . Goals::WORDING . '.';
        }
        $about = 'Goal: ' . Goals::WORDING . ', in one session. Only check questions count; practice questions, answers after help and retries do not. For ' . $this->words->title('Q07') . ' (Q07) and '
            . $this->words->title('E06') . ' (E06) the three must include three kinds of question. This is a check from this session only, not a long-term learning claim. '
            . ($provider === 'mock' ? 'Simulated tutor (mock mode): the tutor\'s wording came from a simulated model, so this is not evidence of how a live model behaves or of learning.' : 'Live provider: ' . $provider . '.');
        $section = fn (string $heading, array $lines, string $empty) => ['heading' => $heading, 'lines' => SummaryView::unique(array_map(fn ($l) => $this->words->text($l), $lines)), 'empty' => $empty];
        return [
            'status' => match ($status) {
                'COMPLETED' => 'Completed',
                'INTERRUPTED' => 'Interrupted',
                default => 'In progress',
            },
            'topics' => array_map(fn ($t) => $this->words->title($t) . ' (' . $t . ')', $topics),
            'skills' => $skills,
            'sections' => [
                $section('Goals met', $goalLines, 'No goal was met in this session.'),
                $section('Practice and help', $practice, 'No practice with the tutor in this session.'),
                $section('Route taken', $routeLines, 'The session stayed on the topic.'),
                $section('Small slips', $slipLines, 'No slips were confirmed.'),
                $section('Answers read from the student\'s wording', $readLines, 'Every answer was read as typed.'),
                $section('Held check answers', $heldLines, 'No check answers were held.'),
                ...($corrected === [] ? [] : [$section('Corrections', $this->correctionLines($corrected), '')]),
                ['heading' => 'Before check questions (audit)', 'lines' => SummaryView::unique($auditLines), 'empty' => 'No forward-looking words were used before a check question.'],
                $section('Still open or not checked', $open, 'Nothing listed.'),
                $section('What would show more', $next, 'Nothing listed.'),
                $section(SummaryView::EARLIER, $this->words->earlier($earlier, true), 'No results from earlier sessions.'),
                $section('About these results', [$about], ''),
            ],
        ];
    }

    // ------------------------------------------------------- report record

    private function record(array $snap, array $attempts, array $goals, array $perSkill, array $topics, array $moves, string $status, string $provider, \DateTimeImmutable $now): array
    {
        $scope = 'Each answer was compared exactly with the answer the app computed; reasoning was not assessed.';
        $groups = [];
        $transfer = [];
        $hyp = [];
        $heldNotes = [];
        foreach ($attempts as $a) {
            if (($a['held'] ?? null) !== null) {
                $heldNotes[] = $a['skill_id'] . ': a check answer is ' . (self::HELD_WORDS[$a['held']] ?? 'held') . '; it is not counted as correct or as not yet.';
                continue;
            }
            if ($a['purpose'] === 'CHECK' && $a['help'] === []) {
                $k = $a['skill_id'] . '|' . $a['verdict'];
                $groups[$k] ??= ['skill_id' => $a['skill_id'], 'verdict' => $a['verdict'], 'ids' => []];
                $groups[$k]['ids'][] = $a['attempt_id'];
                if ($a['verdict'] === 'CORRECT' && $a['variant_id'] === 'transfer') {
                    $transfer[] = ['statement' => 'New kind of question answered correctly without help: ' . $a['prompt'], 'skill_id' => $a['skill_id'], 'component_id' => null,
                        'event_ids' => [$a['attempt_id']], 'review_scope' => $scope . ' Only this specific new form was tested.'];
                }
            }
            foreach ($a['signatures'] as $code) {
                if (isset(SummaryView::PATTERNS[$code])) {
                    $hyp[] = ['kind' => 'OBSERVATION', 'statement' => $a['skill_id'] . ': the answer ' . preg_replace('/^were /', 'was ', SummaryView::PATTERNS[$code]) . ' (observation only; not a confirmed cause).',
                        'evidence_event_ids' => [$a['attempt_id']], 'hypothesis_status' => null];
                }
            }
        }
        $unresolved = array_merge(array_slice($heldNotes, 0, 20), ['Skills not listed above were not tested in this session and carry no pass or fail label.', 'No later review check exists yet, so no retention evidence was collected.']);
        $report = [
            'report_id' => Ids::make('rep'),
            'session_id' => $snap['session_id'],
            'state_revision' => $snap['revision'],
            'architecture_version' => '1.7',
            'curriculum_version' => CurriculumRepository::CURRICULUM_VERSION,
            'status' => $status,
            'targets_attempted' => $topics,
            'independent_evidence' => array_values(array_map(fn ($g) => [
                'statement' => $g['skill_id'] . ': ' . count($g['ids']) . ' check ' . (count($g['ids']) === 1 ? 'answer' : 'answers') . ' without help, ' . strtolower($g['verdict']) . '.',
                'skill_id' => $g['skill_id'], 'component_id' => null, 'event_ids' => array_slice($g['ids'], 0, 100), 'review_scope' => $scope,
            ], $groups)),
            'guided_work' => array_values(array_map(fn ($s) => [
                'statement' => $s['skill_id'] . ': ' . $s['practice'] . ' practice or retry question(s) with the tutor; ' . $s['practice_correct'] . ' correct. These do not count toward any goal.',
                'skill_id' => $s['skill_id'], 'component_id' => null, 'event_ids' => [], 'review_scope' => 'Practice with the tutor.',
            ], array_filter($perSkill, fn ($s) => $s['practice'] > 0))),
            'repairs_and_returns' => array_map(fn ($m) => [
                'statement' => 'The tutor moved from ' . $m['from'] . ' to ' . $m['to'] . '.', 'skill_id' => $m['to'], 'component_id' => null,
                'event_ids' => [$m['event_id']], 'review_scope' => 'The tutor\'s choice of route; not a diagnosis of cause.',
            ], array_slice($moves, 0, 100)),
            'immediate_criteria' => array_values(array_map(fn ($g) => [
                'statement' => $g['skill_id'] . ': goal met: ' . Goals::WORDING . '. A check from this session only; no long-term learning claim.',
                'skill_id' => $g['skill_id'], 'component_id' => $g['three_variant'] ? 'three_variant_criterion' : 'numeric_component', 'event_ids' => $g['met_attempt_ids'],
                'review_scope' => 'In-session goal computed by the app from check questions only.',
            ], array_filter($goals, fn ($g) => $g['met']))),
            'transfer_evidence' => $transfer,
            'retention_evidence' => [],
            'hypotheses' => array_slice($hyp, 0, 100),
            'unresolved_or_untested' => $unresolved,
            'next_evidence_needed' => array_map(fn ($t) => ($goals[$t]['met'] ?? false) ? 'A later independent review of ' . $t . '.' : 'Fresh check questions on ' . $t . ' without help.', $topics),
            'policy_note' => 'Goal: ' . Goals::WORDING . ' (check questions only). ' . ($provider === 'mock' ? 'MOCK PROVIDER: the tutor\'s wording came from a simulated model; this is not evidence of live-model behaviour or of learning effectiveness.' : 'Live provider: ' . $provider . '.'),
            'created_at' => Time::iso($now),
        ];
        $errors = $this->schemas->errors($report, 'report.schema.json');
        if ($errors !== []) {
            throw new \LogicException('Report failed report.schema.json: ' . implode('; ', $errors));
        }
        return $report;
    }
}
