<?php
declare(strict_types=1);

namespace AlgebraEngine;

/**
 * Server-side configuration. Values come from the private config.php only;
 * nothing here is client-selectable.
 */
final class Config
{
    public const DEFAULTS = [
        'resources_dir' => null,            // defaults to private/resources
        'db_path' => null,                  // defaults to private/var/algebra.sqlite
        'provider' => 'mock',               // 'mock' or 'openai_compatible'
        'provider_base_url' => '',
        'provider_api_key' => '',
        'provider_model' => '',
        'provider_connect_timeout' => 10,
        'provider_timeout' => 45,
        'provider_max_tokens' => 4096,      // reasoning models spend part of this on hidden reasoning
        'provider_thinking' => '',          // live provider: 'disabled' or 'enabled'; required, never left to the provider default
        'provider_reasoning_effort' => '',  // 'low', 'high' or 'max'; required when thinking is enabled, empty otherwise
        'lease_seconds' => 90,
        'session_idle_ttl' => 86400,
        'session_absolute_ttl' => 604800,
        'cookie_name' => 'ae_sid',
        'cookie_secure' => 'auto',          // 'auto' follows HTTPS; true/false to force
        'base_path' => null,                // cookie path; defaults to the api.php directory
        'max_model_calls_per_session' => 150,
        'global_provider_calls_per_day' => 2000,
        'starts_per_container_per_hour' => 30,
        'global_starts_per_hour' => 300,
        'request_body_max' => 16384,
        'answer_max' => 2000,
        'working_max' => 4000,
        'provider_result_max' => 65536,
        'default_session_minutes' => 20,
        'continuation_target_items' => 10,
        'access_gate_token' => '',          // optional private-pilot gate
        'mock_fault_file' => '',            // test harness only; never set in production
        'display_errors' => false,
    ];

    /** @param array<string,mixed> $values */
    private function __construct(private array $values)
    {
    }

    /** @param array<string,mixed> $overrides */
    public static function fromArray(array $overrides, ?string $privateRoot = null): self
    {
        $unknown = array_diff(array_keys($overrides), array_keys(self::DEFAULTS));
        if ($unknown !== []) {
            throw new \InvalidArgumentException('Unknown configuration keys: ' . implode(', ', $unknown));
        }
        $values = array_merge(self::DEFAULTS, $overrides);
        $privateRoot ??= dirname(__DIR__);
        $values['resources_dir'] ??= $privateRoot . '/resources';
        $values['db_path'] ??= $privateRoot . '/var/algebra.sqlite';
        if (!in_array($values['provider'], ['mock', 'openai_compatible'], true)) {
            throw new \InvalidArgumentException('provider must be mock or openai_compatible');
        }
        return new self($values);
    }

    public function get(string $key): mixed
    {
        if (!array_key_exists($key, $this->values)) {
            throw new \InvalidArgumentException('Unknown configuration key: ' . $key);
        }
        return $this->values[$key];
    }

    public function int(string $key): int
    {
        return (int) $this->get($key);
    }

    public function string(string $key): string
    {
        return (string) $this->get($key);
    }

    public function isMock(): bool
    {
        return $this->values['provider'] === 'mock';
    }

    /** @param array<string,mixed> $overrides */
    public function with(array $overrides): self
    {
        return new self(array_merge($this->values, $overrides));
    }
}
