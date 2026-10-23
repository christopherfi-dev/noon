<?php
declare(strict_types=1);

namespace AlgebraEngine\Items;

use AlgebraEngine\Curriculum\CurriculumRepository;
use AlgebraEngine\Math\ExpressionParser;
use AlgebraEngine\Math\MathInputError;
use AlgebraEngine\Math\Polynomial;
use AlgebraEngine\Math\ScalarParser;
use AlgebraEngine\Provider\SchemaValidator;
use AlgebraEngine\Support\RandomSource;

/**
 * Item gate (spec 04): the model's draft only proposes a template and
 * parameters. The host checks the parameters, renders the prompt, derives
 * the key, checks freshness and mints the accepted record. A model-proposed
 * expected answer never becomes the key; a disagreement flags the item.
 */
final class AcceptanceGate
{
    public const GUIDED_TEMPLATE = 'GUIDED_STEP';
    /** Round 6 (§5): the host-built micro-question on the faulty sub-step of a slip candidate. */
    public const MICRO_CHECK_TEMPLATE = 'MICRO_CHECK';

    public function __construct(
        private ItemFactory $factory,
        private SchemaValidator $schemas,
    ) {
    }

    /**
     * @param array<string,mixed> $draft item object from the envelope
     * @param array<string,mixed> $allocation host allocation (item_id, item_version, role, skill_id, variant_id, component_id, template_ids, fixed_parameters, avoid_parameters, guided_step)
     * @param list<string> $usedFingerprints fingerprints already presented in this session
     * @return array{ok:bool,errors:list<string>,record:?array,fingerprint:?string,flags:list<array>,host_generated:bool}
     */
    public function accept(array $draft, array $allocation, array $usedFingerprints, RandomSource $rng): array
    {
        $errors = [];
        foreach (['item_id', 'item_version', 'skill_id', 'role', 'variant_id', 'component_id'] as $k) {
            if (($draft[$k] ?? null) !== $allocation[$k]) {
                $errors[] = "item.$k must be " . json_encode($allocation[$k]);
            }
        }
        $templateId = $draft['template_id'] ?? null;
        if (!in_array($templateId, $allocation['template_ids'], true)) {
            $errors[] = 'item.template_id must be one of ' . implode(', ', $allocation['template_ids']);
        }
        if ($errors !== []) {
            return self::fail($errors);
        }

        if ($templateId === self::GUIDED_TEMPLATE) {
            return $this->acceptGuided($draft, $allocation);
        }
        if ($templateId === self::MICRO_CHECK_TEMPLATE) {
            return $this->acceptMicroCheck($draft, $allocation);
        }
        // Round 6 (§5): a slip retry re-asks the same question with the same numbers; it never counts either way.
        $retry = isset($allocation['retry_of']);
        if ($retry && ($draft['parameters'] ?? null) != $allocation['fixed_parameters']) {
            return self::fail(['item.parameters must equal the fixed_parameters in the allocation exactly']);
        }

        $template = $this->factory->get($templateId);
        if (($draft['response_type'] ?? null) !== $template->responseType()) {
            $errors[] = 'item.response_type must be ' . $template->responseType() . ' for ' . $templateId;
        }
        $params = $draft['parameters'] ?? [];
        $hostGenerated = false;
        $keep = $allocation['keep_features'] ?? [];
        $avoidAnswers = $allocation['avoid_answers'] ?? [];
        if ($params === [] || $params === null) {
            for ($i = 0; $i < 400; $i++) {
                $params = $template->generate($rng, $allocation['avoid_parameters'] ?? null);
                if (StructuralFeatures::mismatches($keep, $templateId, $params) === [] && self::avoidedAnswer($template->answerSpec($params), $avoidAnswers) === null) {
                    break;
                }
            }
            $hostGenerated = true;
        } else {
            $errors = array_merge($errors, $template->validate($params));
            $avoid = $allocation['avoid_parameters'] ?? null;
            if ($errors === [] && $avoid !== null && !$this->varies($params, $avoid)) {
                $errors[] = 'parameters must vary the implicated component: every numeric parameter must differ from the previous item';
            }
            // R4-B: a proposal may not change a structural feature that must be kept; the bounded repair path follows.
            $changed = $errors === [] ? StructuralFeatures::mismatches($keep, $templateId, $params) : [];
            if ($changed !== []) {
                $errors[] = 'parameters must keep the structure of the item the learner failed: ' . implode(', ', $changed);
            }
            // Round 6 (§4): the reply before this item refers back to the previous one, so the new answer must not be one of its numbers.
            if ($errors === [] && ($hit = self::avoidedAnswer($template->answerSpec($params), $avoidAnswers)) !== null) {
                $errors[] = 'parameters give the answer ' . $hit . ', which is also a number in the previous problem or answer; choose other parameters';
            }
        }
        if ($errors !== []) {
            return self::fail($errors);
        }
        $fingerprint = ItemFactory::fingerprint($templateId, $params + ($retry ? ['retry_of' => $allocation['retry_of']] : []));
        if (in_array($fingerprint, $usedFingerprints, true)) {
            return self::fail(['parameters reuse an item instance already presented in this session; choose fresh parameters']);
        }

        $spec = $template->answerSpec($params);
        $flags = [];
        $proposed = $draft['answer_spec']['expected'] ?? null;
        if ($proposed !== null && !self::sameKey($spec, $proposed, $template->mathematicalObjects($params)['variables'] ?? ['x'])) {
            $flags[] = ['flag' => 'MODEL_KEY_DISAGREEMENT', 'model_expected' => $proposed, 'host_expected' => $spec['expected']];
        }
        $record = [
            'item_id' => $allocation['item_id'],
            'item_version' => $allocation['item_version'],
            'curriculum_id' => CurriculumRepository::CURRICULUM_ID,
            'curriculum_version' => CurriculumRepository::CURRICULUM_VERSION,
            'skill_id' => $allocation['skill_id'],
            'role' => $allocation['role'],
            'component_id' => $allocation['component_id'],
            'variant_id' => $allocation['variant_id'],
            'prompt' => $template->render($params),
            'response_type' => $template->responseType(),
            'template_id' => $templateId,
            'parameters' => $params,
            'mathematical_objects' => $template->mathematicalObjects($params),
            'domain' => ['number_system' => 'RATIONAL', 'restrictions' => []],
            'answer_spec' => $spec,
            'checking' => [
                'requested_route' => 'DETERMINISTIC_TEMPLATE',
                'required_capabilities' => [$template->responseType() === 'expression' ? 'symbolic_equivalence_with_domain' : ($template->responseType() === 'classification' ? 'classification_exact_set' : 'exact_numeric_or_declared_tolerance')],
                'scope' => $template->checkingScope(),
            ],
            'representation_requirements' => [],
            'support_already_supplied' => [],
            'independent_eligible_proposal' => !$retry && in_array($allocation['role'], ['TARGET', 'INDEPENDENT', 'PREREQUISITE', 'TRANSFER', 'DIAGNOSTIC'], true),
        ];
        if ($retry) {
            $record['support_already_supplied'] = ['slip_retry'];
        }
        $schemaErrors = $this->schemas->errors($record, 'item.schema.json');
        if ($schemaErrors !== []) {
            throw new \LogicException('Host-built item failed item.schema.json: ' . implode('; ', $schemaErrors));
        }
        return ['ok' => true, 'errors' => [], 'record' => $record, 'fingerprint' => $fingerprint, 'flags' => $flags, 'host_generated' => $hostGenerated];
    }

    /** Guided steps are fully host-defined by the plan; the model must copy the fixed parameters. */
    private function acceptGuided(array $draft, array $allocation): array
    {
        if (($draft['parameters'] ?? null) != $allocation['fixed_parameters']) {
            return self::fail(['item.parameters must equal the fixed_parameters in the allocation exactly']);
        }
        $step = $allocation['guided_step'];
        if (($draft['response_type'] ?? null) !== $step['response_type']) {
            return self::fail(['item.response_type must be ' . $step['response_type']]);
        }
        $objects = ['plan_problem' => $allocation['guided_problem'], 'variables' => $step['variables'], 'step_id' => $step['id']];
        if ($step['options'] !== null) {
            $objects['options'] = $step['options'];
            $objects['option_letters'] = array_column($step['options'], 'letter');
        }
        $record = [
            'item_id' => $allocation['item_id'],
            'item_version' => $allocation['item_version'],
            'curriculum_id' => CurriculumRepository::CURRICULUM_ID,
            'curriculum_version' => CurriculumRepository::CURRICULUM_VERSION,
            'skill_id' => $allocation['skill_id'],
            'role' => 'GUIDED',
            'component_id' => $allocation['component_id'],
            'variant_id' => $allocation['variant_id'],
            'prompt' => $allocation['fixed_parameters']['phrasing'] === 'alt' && $step['alt_prompt'] !== null ? $step['alt_prompt'] : $step['prompt'],
            'response_type' => $step['response_type'],
            'template_id' => self::GUIDED_TEMPLATE,
            'parameters' => $allocation['fixed_parameters'],
            'mathematical_objects' => $objects,
            'domain' => ['number_system' => 'RATIONAL', 'restrictions' => []],
            'answer_spec' => $step['answer'],
            'checking' => [
                'requested_route' => 'DETERMINISTIC_TEMPLATE',
                'required_capabilities' => ['guided_step_' . $step['response_type']],
                'scope' => 'Guided repair step checked against a host-derived key; assisted work, never independent evidence.',
            ],
            'representation_requirements' => [],
            'support_already_supplied' => ['guided_repair_step'],
            'independent_eligible_proposal' => false,
        ];
        $schemaErrors = $this->schemas->errors($record, 'item.schema.json');
        if ($schemaErrors !== []) {
            throw new \LogicException('Host-built guided item failed item.schema.json: ' . implode('; ', $schemaErrors));
        }
        return ['ok' => true, 'errors' => [], 'record' => $record, 'fingerprint' => ItemFactory::fingerprint(self::GUIDED_TEMPLATE, $allocation['fixed_parameters'] + ['item' => $allocation['item_id']]), 'flags' => [], 'host_generated' => false];
    }

    /** The micro-check is fully host-defined: "What is 7 × 5?", keyed by the sub-step's value. Not counted toward any goal. */
    private function acceptMicroCheck(array $draft, array $allocation): array
    {
        $fixed = $allocation['fixed_parameters'];
        if (($draft['parameters'] ?? null) != $fixed) {
            return self::fail(['item.parameters must equal the fixed_parameters in the allocation exactly']);
        }
        if (($draft['response_type'] ?? null) !== 'scalar') {
            return self::fail(['item.response_type must be scalar']);
        }
        $record = [
            'item_id' => $allocation['item_id'],
            'item_version' => $allocation['item_version'],
            'curriculum_id' => CurriculumRepository::CURRICULUM_ID,
            'curriculum_version' => CurriculumRepository::CURRICULUM_VERSION,
            'skill_id' => $allocation['skill_id'],
            'role' => $allocation['role'],
            'component_id' => $allocation['component_id'],
            'variant_id' => $allocation['variant_id'],
            'prompt' => 'What is ' . $fixed['display'] . '?',
            'response_type' => 'scalar',
            'template_id' => self::MICRO_CHECK_TEMPLATE,
            'parameters' => $fixed,
            'mathematical_objects' => ['sub_step' => $fixed['display'], 'variables' => []],
            'domain' => ['number_system' => 'RATIONAL', 'restrictions' => []],
            'answer_spec' => ['kind' => 'exact_rational', 'expected' => (string) $fixed['value'], 'requested_form' => 'single_number', 'units' => null, 'tolerance' => null, 'rubric' => []],
            'checking' => [
                'requested_route' => 'DETERMINISTIC_TEMPLATE',
                'required_capabilities' => ['exact_numeric_or_declared_tolerance'],
                'scope' => 'Micro-question on one arithmetic sub-step of a slip candidate; checked against a host-derived value; never counted toward a goal.',
            ],
            'representation_requirements' => [],
            'support_already_supplied' => ['slip_micro_check'],
            'independent_eligible_proposal' => false,
        ];
        $schemaErrors = $this->schemas->errors($record, 'item.schema.json');
        if ($schemaErrors !== []) {
            throw new \LogicException('Host-built micro-check failed item.schema.json: ' . implode('; ', $schemaErrors));
        }
        return ['ok' => true, 'errors' => [], 'record' => $record, 'fingerprint' => ItemFactory::fingerprint(self::MICRO_CHECK_TEMPLATE, $fixed + ['item' => $allocation['item_id']]), 'flags' => [], 'host_generated' => false];
    }

    /** A whole-number key whose size is one of the numbers to avoid. @param list<string> $avoid */
    public static function avoidedAnswer(array $spec, array $avoid): ?string
    {
        if ($avoid === [] || $spec['kind'] !== 'exact_rational') {
            return null;
        }
        try {
            $v = ScalarParser::parse((string) $spec['expected']);
        } catch (MathInputError) {
            return null;
        }
        return $v->isInteger() && in_array((string) abs($v->num()), $avoid, true) ? (string) $spec['expected'] : null;
    }

    private function varies(array $p, array $avoid): bool
    {
        foreach ($p as $k => $v) {
            if (is_int($v) && isset($avoid[$k]) && $avoid[$k] === $v && in_array($k, ['a', 'b', 'c'], true)) {
                return false;
            }
        }
        return true;
    }

    private static function sameKey(array $spec, mixed $proposed, array $vars): bool
    {
        try {
            return match ($spec['kind']) {
                'exact_rational' => (is_string($proposed) || is_int($proposed)) && ScalarParser::parse((string) $proposed)->equals(ScalarParser::parse($spec['expected'])),
                'polynomial' => is_string($proposed) && Polynomial::fromNode(ExpressionParser::parse($proposed, $vars))->equals(Polynomial::fromNode(ExpressionParser::parse($spec['expected'], $vars))),
                'classification' => is_array($proposed) && self::sortedLetters($proposed) === self::sortedLetters($spec['expected']),
                default => false,
            };
        } catch (MathInputError) {
            return false;
        }
    }

    private static function sortedLetters(array $l): array
    {
        $l = array_map(fn ($x) => strtoupper((string) $x), $l);
        sort($l);
        return $l;
    }

    private static function fail(array $errors): array
    {
        return ['ok' => false, 'errors' => $errors, 'record' => null, 'fingerprint' => null, 'flags' => [], 'host_generated' => false];
    }
}
