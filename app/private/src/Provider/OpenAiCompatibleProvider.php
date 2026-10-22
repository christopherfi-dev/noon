<?php
declare(strict_types=1);

namespace AlgebraEngine\Provider;

use AlgebraEngine\Config;

/**
 * Configurable OpenAI-compatible chat-completions adapter (DeepSeek by
 * default in the package). No streaming, no tools, no web or file access.
 * The operator must configure the base URL, model, key and thinking mode
 * explicitly (round 3 ruling): thinking is sent on every call and never left
 * to the provider default. Reasoning text in the response is never read,
 * stored or sent back; only its token count is kept.
 */
final class OpenAiCompatibleProvider implements ProviderAdapter
{
    public function __construct(private Config $config, private HttpTransport $transport)
    {
        foreach (['provider_base_url', 'provider_model', 'provider_api_key'] as $k) {
            if (trim($config->string($k)) === '') {
                throw new \InvalidArgumentException("Live provider requires $k in the private configuration");
            }
        }
        $problem = self::thinkingProblem($config->string('provider_thinking'), $config->string('provider_reasoning_effort'));
        if ($problem !== null) {
            throw new \InvalidArgumentException($problem);
        }
    }

    /** Null when the thinking settings are explicit and consistent, else the reason. */
    public static function thinkingProblem(string $thinking, string $effort): ?string
    {
        if (!in_array($thinking, ['disabled', 'enabled'], true)) {
            return "provider_thinking must be 'disabled' or 'enabled' (the provider default is never relied on)";
        }
        if ($thinking === 'enabled' && !in_array($effort, ['low', 'high', 'max'], true)) {
            return "provider_reasoning_effort must be 'low', 'high' or 'max' when provider_thinking is 'enabled'";
        }
        if ($thinking === 'disabled' && $effort !== '') {
            return "provider_reasoning_effort applies only when provider_thinking is 'enabled'; leave it empty";
        }
        return null;
    }

    public function name(): string
    {
        return 'openai_compatible';
    }

    public function model(): string
    {
        return $this->config->string('provider_model');
    }

    public function complete(array $messages, bool $jsonMode): ProviderResponse
    {
        $thinking = $this->config->string('provider_thinking');
        $body = [
            'model' => $this->model(),
            'messages' => $messages,
            'stream' => false,
            'max_tokens' => $this->config->int('provider_max_tokens'),
            'thinking' => ['type' => $thinking],
        ];
        if ($thinking === 'enabled') {
            $body['reasoning_effort'] = $this->config->string('provider_reasoning_effort');
        } else {
            $body['temperature'] = 0.2; // has no effect in thinking mode, so it is sent only without it
        }
        if ($jsonMode) {
            $body['response_format'] = ['type' => 'json_object'];
        }
        $url = rtrim($this->config->string('provider_base_url'), '/') . '/chat/completions';
        $start = microtime(true);
        $result = $this->transport->post(
            $url,
            ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $this->config->string('provider_api_key')],
            json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            $this->config->int('provider_connect_timeout'),
            $this->config->int('provider_timeout'),
            $this->config->int('provider_result_max'),
        );
        if ($result['error'] !== null) {
            return ProviderResponse::failure($result['error'], $result['status'], $result['diagnostics'] ?? null);
        }
        if ($result['status'] < 200 || $result['status'] >= 300) {
            return ProviderResponse::failure('HTTP_ERROR', $result['status']);
        }
        $decoded = json_decode($result['body'], true, 32);
        $choice = is_array($decoded) ? ($decoded['choices'][0] ?? null) : null;
        if (!is_array($choice)) {
            return ProviderResponse::failure('BAD_RESPONSE', $result['status']);
        }
        // Only content is read from the message; reasoning_content is ignored.
        $content = $choice['message']['content'] ?? null;
        $u = is_array($decoded['usage'] ?? null) ? $decoded['usage'] : [];
        $usage = [
            'prompt_tokens' => isset($u['prompt_tokens']) ? (int) $u['prompt_tokens'] : null,
            'completion_tokens' => isset($u['completion_tokens']) ? (int) $u['completion_tokens'] : null,
            'reasoning_tokens' => isset($u['completion_tokens_details']['reasoning_tokens']) ? (int) $u['completion_tokens_details']['reasoning_tokens'] : null,
        ];
        return new ProviderResponse(is_string($content) ? $content : null, $choice['finish_reason'] ?? null, null, $result['status'], $usage,
            (int) round((microtime(true) - $start) * 1000));
    }
}
