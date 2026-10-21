<?php
declare(strict_types=1);

namespace AlgebraEngine\Provider;

use AlgebraEngine\Config;

/**
 * Configurable OpenAI-compatible chat-completions adapter (DeepSeek by
 * default in the package). No streaming, no tools, no web or file access.
 * The operator must configure the base URL, model and key explicitly.
 */
final class OpenAiCompatibleProvider implements ProviderAdapter
{
    public function __construct(private readonly Config $config, private readonly HttpTransport $transport)
    {
        foreach (['provider_base_url', 'provider_model', 'provider_api_key'] as $k) {
            if (trim($config->string($k)) === '') {
                throw new \InvalidArgumentException("Live provider requires $k in the private configuration");
            }
        }
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
        $body = [
            'model' => $this->model(),
            'messages' => $messages,
            'stream' => false,
            'max_tokens' => $this->config->int('provider_max_tokens'),
            'temperature' => 0.2,
        ];
        if ($jsonMode) {
            $body['response_format'] = ['type' => 'json_object'];
        }
        $url = rtrim($this->config->string('provider_base_url'), '/') . '/chat/completions';
        $result = $this->transport->post(
            $url,
            ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $this->config->string('provider_api_key')],
            json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            $this->config->int('provider_connect_timeout'),
            $this->config->int('provider_timeout'),
            $this->config->int('provider_result_max'),
        );
        if ($result['error'] !== null) {
            return ProviderResponse::failure($result['error'], $result['status']);
        }
        if ($result['status'] < 200 || $result['status'] >= 300) {
            return ProviderResponse::failure('HTTP_ERROR', $result['status']);
        }
        $decoded = json_decode($result['body'], true, 32);
        $choice = is_array($decoded) ? ($decoded['choices'][0] ?? null) : null;
        if (!is_array($choice)) {
            return ProviderResponse::failure('BAD_RESPONSE', $result['status']);
        }
        $content = $choice['message']['content'] ?? null;
        return new ProviderResponse(is_string($content) ? $content : null, $choice['finish_reason'] ?? null, null, $result['status']);
    }
}
