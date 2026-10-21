<?php
declare(strict_types=1);

namespace AlgebraEngine\Provider;

use AlgebraEngine\Items\ItemFactory;
use AlgebraEngine\Support\Json;
use AlgebraEngine\Support\RandomSource;

/**
 * Deterministic simulated model. It makes no network calls. Its output goes
 * through exactly the same host acceptance pipeline as a live provider's.
 * Tests queue faults to exercise every rejection path; a fault file can be
 * configured for browser tests only.
 */
final class MockProvider implements ProviderAdapter
{
    /** @var list<array<string,mixed>> */
    public array $faults = [];
    /** Test hook invoked during a call (used to interleave a concurrent request). */
    public ?\Closure $onCall = null;
    public int $calls = 0;
    /** @var list<array{json:bool,messages:list<array>}> */
    public array $log = [];

    public function __construct(private readonly ItemFactory $factory, private readonly string $faultFile = '')
    {
    }

    public function name(): string
    {
        return 'mock';
    }

    public function model(): string
    {
        return 'mock-deterministic-v1';
    }

    public function complete(array $messages, bool $jsonMode): ProviderResponse
    {
        $this->calls++;
        $this->log[] = ['json' => $jsonMode, 'messages' => $messages];
        $fault = $this->nextFault($jsonMode ? 'envelope' : 'binding');
        if (($fault['type'] ?? null) === 'CALLBACK' || $this->onCall !== null) {
            $hook = $this->onCall;
            $this->onCall = null;
            if ($hook !== null) {
                $hook();
            }
        }
        if ($fault !== null) {
            switch ($fault['type']) {
                case 'TIMEOUT':
                    return ProviderResponse::failure('TIMEOUT');
                case 'HTTP_500':
                    return ProviderResponse::failure('HTTP_ERROR', 500);
                case 'EMPTY':
                    return new ProviderResponse('', 'stop');
                case 'INVALID_JSON':
                    return new ProviderResponse('Here is the JSON you asked for.', 'stop');
                case 'HALT':
                    return new ProviderResponse('ACE_HALT: CONTRACT_CONFLICT | binding=NOT_ESTABLISHED');
                case 'BAD_HANDSHAKE':
                    return new ProviderResponse('Understood. ' . $this->handshake($messages));
                case 'OLD_HANDSHAKE':
                    return new ProviderResponse(str_replace('architecture=1.2', 'architecture=1.1', $this->handshake($messages)));
            }
        }
        if (!$jsonMode) {
            return new ProviderResponse($this->handshake($messages));
        }
        $envelope = $this->envelope($messages);
        if ($fault !== null) {
            $envelope = $this->applyFault($envelope, $fault);
            if ($fault['type'] === 'TRUNCATED') {
                $json = Json::encode($envelope);
                return new ProviderResponse(substr($json, 0, intdiv(strlen($json), 2)), 'length');
            }
        }
        return new ProviderResponse(Json::encode($envelope), 'stop');
    }

    /** The mock "reads" the bound architecture and answers with its Section 18 line. */
    private function handshake(array $messages): string
    {
        $arch = $messages[0]['content'] ?? '';
        if (preg_match('/^(ACE_BOUND: [^\n]+)$/m', $arch, $m)) {
            return trim($m[1]);
        }
        return 'ACE_HALT: CONTRACT_CONFLICT | binding=NOT_ESTABLISHED';
    }

    private function context(array $messages): array
    {
        foreach ($messages as $m) {
            if ($m['role'] === 'user' && str_starts_with($m['content'], 'JSON ')) {
                $data = Json::decode(substr($m['content'], 5), 64);
                if (isset($data['TRUSTED_OUTPUT_ALLOCATION'])) {
                    return $data;
                }
            }
        }
        throw new \RuntimeException('Mock received no trusted context');
    }

    private function envelope(array $messages): array
    {
        $ctx = $this->context($messages);
        $alloc = $ctx['TRUSTED_OUTPUT_ALLOCATION'];
        $item = null;
        if ($alloc['required_message_type'] === 'QUESTION') {
            // Like a cooperative model, propose different numbers after each repair request.
            $repairs = count(array_filter($messages, fn ($m) => $m['role'] === 'user' && str_contains($m['content'], '"REPAIR_REQUEST"')));
            $item = $this->draftItem($alloc, $repairs);
        }
        $planned = $alloc['host_planned_transition'] ?? null;
        return [
            'architecture_version' => '1.2',
            'session_id' => $ctx['TRUSTED_STATE']['session_id'],
            'state_revision' => $ctx['TRUSTED_STATE']['revision'],
            'reply_to_event_id' => $ctx['TRUSTED_EVENT']['event_id'],
            'message_type' => $alloc['required_message_type'],
            'student_message' => $this->message($alloc),
            'item' => $item,
            'proposed_transition' => $planned === null ? null : [
                'to_mode' => $planned['to'],
                'target_skill_id' => $planned['skill_id'],
                'reason' => 'Following the host-selected operation.',
                'evidence_event_ids' => [],
            ],
            'evidence_proposals' => [],
            'resource_requests' => [],
            'teacher_report' => null,
            'issue' => null,
        ];
    }

    private function draftItem(array $alloc, int $repairs = 0): array
    {
        $templateId = $alloc['allowed_templates'][0]['template_id'];
        $expected = null;
        $responseType = $alloc['allowed_templates'][0]['response_type'];
        if (isset($alloc['fixed_parameters'])) {
            $params = $alloc['fixed_parameters'];
        } else {
            $template = $this->factory->get($templateId);
            $params = $template->generate(RandomSource::seeded('mock:' . $alloc['item_id'] . ($repairs > 0 ? ':repair' . $repairs : '')), $alloc['avoid_parameters'] ?? null);
            $expected = $template->answerSpec($params)['expected'];
        }
        return [
            'item_id' => $alloc['item_id'],
            'item_version' => $alloc['item_version'],
            'curriculum_id' => 'ACE-ALGEBRA1',
            'curriculum_version' => '1.1.0',
            'skill_id' => $alloc['skill_id'],
            'role' => $alloc['role'],
            'component_id' => $alloc['component_id'],
            'variant_id' => $alloc['variant_id'],
            'prompt' => 'Rendered by host.',
            'response_type' => $responseType,
            'template_id' => $templateId,
            'parameters' => $params,
            'mathematical_objects' => new \stdClass(),
            'domain' => ['number_system' => 'RATIONAL', 'restrictions' => []],
            'answer_spec' => ['kind' => 'proposal', 'expected' => $expected, 'requested_form' => null, 'units' => null, 'tolerance' => null, 'rubric' => []],
            'checking' => ['requested_route' => 'DETERMINISTIC_TEMPLATE', 'required_capabilities' => [], 'scope' => 'Proposed; host checks.'],
            'representation_requirements' => [],
            'support_already_supplied' => [],
            'independent_eligible_proposal' => $alloc['role'] !== 'GUIDED',
        ];
    }

    private function message(array $alloc): string
    {
        $parts = $alloc['feedback_facts'] ?? [];
        $op = $alloc['operation'];
        if ($op === 'HINT' || $op === 'EXPLANATION') {
            $parts[] = $alloc['hint_focus'] ?? 'Look again at the structure of the question.';
        } elseif ($op === 'GUIDED_STEP') {
            if (!empty($alloc['representation_hint'])) {
                $parts[] = $alloc['representation_hint'];
            }
        } else {
            $parts[] = match ($alloc['role']) {
                'DIAGNOSTIC' => 'Here is another question of a similar kind.',
                'PREREQUISITE' => 'Here is a question on a skill this topic builds on.',
                'INDEPENDENT' => 'Try this one on your own.',
                default => 'Here is a question on this topic.',
            };
        }
        return implode(' ', $parts);
    }

    private function applyFault(array $env, array $fault): array
    {
        switch ($fault['type']) {
            case 'WRONG_KEY':
                if ($env['item'] !== null) {
                    $env['item']['answer_spec']['expected'] = $fault['expected'] ?? '999';
                }
                break;
            case 'TRANSITION':
                $env['proposed_transition'] = $fault['transition'];
                break;
            case 'EVIDENCE':
                $env['evidence_proposals'] = $fault['evidence'];
                break;
            case 'STUDENT_MESSAGE':
                $env['student_message'] = $fault['text'];
                break;
            case 'ITEM_PATCH':
                if ($env['item'] !== null) {
                    $env['item'] = array_merge($env['item'], $fault['patch']);
                }
                break;
            case 'IDENTITY':
                $env['session_id'] = 'ses_forged';
                break;
            case 'MESSAGE_TYPE':
                $env['message_type'] = $fault['message_type'];
                break;
        }
        return $env;
    }

    private function nextFault(string $call): ?array
    {
        if ($this->faultFile !== '' && is_file($this->faultFile)) {
            $queued = json_decode((string) file_get_contents($this->faultFile), true) ?: [];
            $fault = self::take($queued, $call);
            if ($fault !== null) {
                file_put_contents($this->faultFile, json_encode($queued));
                return $fault;
            }
        }
        return self::take($this->faults, $call);
    }

    private static function take(array &$queue, string $call): ?array
    {
        foreach ($queue as $i => $f) {
            $isBinding = in_array($f['type'], ['HALT', 'BAD_HANDSHAKE', 'OLD_HANDSHAKE'], true) || ($f['call'] ?? null) === 'binding';
            if (($call === 'binding') === $isBinding) {
                array_splice($queue, $i, 1);
                return $f;
            }
        }
        return null;
    }
}
