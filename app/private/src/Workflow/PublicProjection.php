<?php
declare(strict_types=1);

namespace AlgebraEngine\Workflow;

use AlgebraEngine\Curriculum\BetaScope;
use AlgebraEngine\Curriculum\CurriculumRepository;
use AlgebraEngine\Evidence\CriteriaCalculator;
use AlgebraEngine\Provider\SchemaValidator;
use AlgebraEngine\Support\LearnerText;

/**
 * The only session data the browser receives. It is built from accepted
 * state, validated against public-view.schema.json and scanned for private
 * keys before it leaves the server.
 */
final class PublicProjection
{
    public const FORBIDDEN_KEYS = [
        'answer_spec', 'expected', 'rubric', 'response_sha256', 'evidence_proposals', 'hypotheses', 'teacher_report',
        'lease_token', 'parameters', 'mathematical_objects', 'signature_answers', 'envelope', 'answer', 'guided',
        'plan', 'diagnosis', 'checker_result_id', 'engine', 'snapshot',
    ];

    public function __construct(private CurriculumRepository $curriculum, private SchemaValidator $schemas)
    {
    }

    public function build(array $row, ?string $pendingOperationId): array
    {
        $snap = $row['snapshot'];
        $eng = $row['engine'];
        $mode = $snap['mode'];
        $status = match (true) {
            $mode === 'SESSION_CLOSED' => 'CLOSED',
            $pendingOperationId !== null => 'PENDING',
            $eng['status'] === 'HOLD' => 'HOLD',
            default => 'READY',
        };
        $target = $snap['original_target_skill_id'];
        $active = $eng['active'];
        $view = [
            'status' => $status,
            'revision' => $snap['revision'],
            'mode' => $mode,
            'target' => $target === null ? null : ['skill_id' => $target, 'title' => $this->curriculum->title($target)],
            'active_item' => $active === null || $status === 'CLOSED' ? null : [
                'item_id' => $active['item_id'],
                'item_version' => $active['item_version'],
                'prompt' => self::prompt($active),
                'response_type' => $active['response_type'],
                'input_help' => $active['input_help'],
                'support_status' => $active['role'] === 'GUIDED' ? 'ASSISTED' : $active['support_status'],
            ],
            'messages' => array_slice(array_map(fn ($m) => ['kind' => $m['kind'], 'text' => mb_substr($m['text'], 0, 6000)], $eng['messages']), 0, 20),
            'menu' => $status === 'CLOSED' ? null : $eng['menu'],
            'progress' => $this->progress($snap, $eng),
            'allowed_actions' => $status === 'PENDING' ? ['END_SESSION'] : $this->allowedActions($snap, $eng),
            'csrf_token' => null,
            'pending_operation_id' => $pendingOperationId,
            'error_code' => null,
        ];
        $this->assertPublic($view);
        return $view;
    }

    /**
     * Round UI (U1): a guided step that does not repeat its problem is shown
     * under it, so the step and any feedback about it never refer to numbers
     * the learner can no longer see. The stored prompt is unchanged.
     */
    private static function prompt(array $active): string
    {
        $problem = $active['role'] === 'GUIDED' ? (string) ($active['objects']['plan_problem'] ?? '') : '';
        return $problem !== '' && !str_contains($active['prompt'], $problem) ? 'Problem: ' . $problem . "\n" . $active['prompt'] : $active['prompt'];
    }

    /** @return list<string> */
    public function allowedActions(array $snap, array $eng): array
    {
        $mode = $snap['mode'];
        if ($mode === 'SESSION_CLOSED') {
            return [];
        }
        if ($mode === 'SESSION_REPORT') {
            return ['END_SESSION'];
        }
        if ($eng['status'] === 'HOLD') {
            return match ($eng['hold_kind']) {
                'GENERATION' => ['RESUME_SESSION', 'CHANGE_TARGET', 'END_SESSION'],
                default => ['CHANGE_TARGET', 'END_SESSION'],
            };
        }
        if (($eng['menu']['menu_id'] ?? null) === 'continuation') {
            return ['CONTINUE_SESSION', 'END_SESSION'];
        }
        if ($mode === 'TARGET_SELECTION' || $mode === 'AWAITING_SESSION_CONTEXT') {
            return ['SELECT_TARGET', 'END_SESSION'];
        }
        $actions = [];
        if ($eng['active'] !== null) {
            $actions = ['SUBMIT_RESPONSE', 'REQUEST_HINT', 'REQUEST_EXPLANATION', 'NOT_LEARNED'];
            if ($eng['active']['role'] !== 'GUIDED') {
                $actions[] = 'SKIP_ITEM';
            }
            if ($eng['active']['unscorable'] ?? false) {
                $actions[] = 'CLARIFY_RESPONSE';
            }
        }
        $actions[] = 'CHANGE_TARGET';
        $actions[] = 'END_SESSION';
        return $actions;
    }

    /**
     * The label's first line names the skill when it is not the topic; the
     * lines after it describe the goal for the item on screen (round UI, U6/U7).
     */
    private function progress(array $snap, array $eng): array
    {
        $skill = $snap['current_skill_id'];
        $episode = $snap['target_episode_id'];
        $streak = 0;
        $whole = false;
        $covered = [];
        foreach ($snap['criteria'] as $c) {
            if ($c['skill_id'] === $skill && $c['target_episode_id'] === $episode) {
                $streak = $c['independent_correct_streak'];
                $whole = $c['whole_skill_requirements_satisfied'] && $c['immediate_status'] === 'IMMEDIATE_CRITERION_MET';
                $covered = $c['covered_variant_ids'];
            }
        }
        $mode = $snap['mode'];
        $single = [
            'TARGET_SELECTION' => 'Choose a skill to start.',
            'AWAITING_SESSION_CONTEXT' => 'Choose a skill to start.',
            'SESSION_REPORT' => 'Session summary ready.',
            'SESSION_CLOSED' => 'Session ended.',
        ];
        if (isset($single[$mode]) || $skill === null) {
            $label = $single[$mode] ?? '';
        } else {
            $lines = [$skill !== $snap['original_target_skill_id'] ? 'Building block: ' . $this->curriculum->title($skill) . '.' : ''];
            $active = $eng['active'];
            if ($active !== null && in_array($active['role'], CriteriaCalculator::COUNTED_ROLES, true)) {
                $lines[] = min($streak, CriteriaCalculator::REQUIRED) . ' of ' . CriteriaCalculator::REQUIRED . ' in a row without help';
                $needed = array_values(array_diff(BetaScope::VARIANTS, $covered));
                if (BetaScope::threeVariant($skill) && $needed !== []) {
                    $lines[] = 'Still needed: ' . BetaScope::kinds($skill, $needed) . '.'; // U7
                }
                if ($active['support_status'] === 'ASSISTED') {
                    $lines[] = 'You had help on this one, so the count starts again after your answer.';
                }
            } elseif ($active !== null && $active['role'] === 'DIAGNOSTIC') {
                $lines[] = 'This question helps choose what to practise. It does not count toward the goal.';
            }
            $label = implode("\n", $lines);
        }
        if (($terms = LearnerText::internalTerms($label)) !== []) {
            throw new \LogicException('Progress label uses internal terms (' . implode(', ', $terms) . ')'); // R5-B
        }
        return ['label' => $label, 'independent_successes' => $streak, 'criterion_required' => CriteriaCalculator::REQUIRED, 'whole_skill_claim' => $whole];
    }

    /** Schema validation of the outgoing projection plus a private-key scan. */
    public function assertPublic(array $view): void
    {
        $errors = $this->schemas->errors($view, 'public-view.schema.json');
        if ($errors !== []) {
            throw new \LogicException('Public view failed public-view.schema.json: ' . implode('; ', $errors));
        }
        self::scan($view);
    }

    public static function scan(mixed $value): void
    {
        if (!is_array($value)) {
            return;
        }
        foreach ($value as $k => $v) {
            if (is_string($k) && in_array($k, self::FORBIDDEN_KEYS, true)) {
                throw new \LogicException('Private key in public payload: ' . $k);
            }
            self::scan($v);
        }
    }
}
