<?php
/*
 * Copy to config.php in the PRIVATE folder (never under public_html) and
 * restrict its permissions (chmod 600). Do not commit config.php.
 *
 * Mock mode works without any key. To run a live smoke test, set provider to
 * 'openai_compatible' and fill in the three provider_* values. Confirm the
 * model name with your provider; none is assumed here.
 */
return [
    'provider' => 'mock',                 // 'mock' or 'openai_compatible'
    'provider_base_url' => '',            // e.g. https://api.deepseek.com (confirm with the provider docs)
    'provider_model' => '',               // operator-confirmed model name
    'provider_api_key' => '',             // secret; server-side only
    'provider_timeout' => 45,
    'provider_connect_timeout' => 10,
    'provider_thinking' => 'per_call',    // round 6 §3.6: code decides per call (thinking for an entry the AI must read, a wrong answer
                                          // with no known pattern, or a question about the maths); or 'disabled' / 'enabled' on every call.
                                          // Always sent explicitly, never left to the provider default.
    'provider_reasoning_effort' => 'low', // 'low', 'high' or 'max' with 'per_call' or 'enabled'; empty with 'disabled'
    // temperature 0.2 is sent while thinking is disabled (it has no effect in thinking mode)
    'provider_max_tokens' => 4096,        // output limit per call; with thinking enabled, reasoning counts against it

    // 'db_path' => '/home/ACCOUNT/algebra-private/var/algebra.sqlite',
    // 'base_path' => '/algebra/',        // cookie path; defaults to the api.php folder

    'max_model_calls_per_session' => 250,  // one call per turn, two when an entry must be read, one repair at most
    'global_provider_calls_per_day' => 2000,   // operator spend ceiling
    'starts_per_container_per_hour' => 30,
    'global_starts_per_hour' => 300,

    // Progress codes (round UI v3): records are deleted this many days after
    // their last use. If this line is missing, 90 applies.
    'progress_retention_days' => 90,

    // Optional private-pilot gate: when set, every request must carry this
    // code (cookie ae_gate or header X-Access-Gate).
    'access_gate_token' => '',

    'cookie_secure' => 'auto',            // keep 'auto' (Secure under HTTPS)
    'display_errors' => false,
];
