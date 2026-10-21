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

    // 'db_path' => '/home/ACCOUNT/algebra-private/var/algebra.sqlite',
    // 'base_path' => '/algebra/',        // cookie path; defaults to the api.php folder

    'max_model_calls_per_session' => 150,
    'global_provider_calls_per_day' => 2000,   // operator spend ceiling
    'starts_per_container_per_hour' => 30,
    'global_starts_per_hour' => 300,

    // Optional private-pilot gate: when set, every request must carry this
    // code (cookie ae_gate or header X-Access-Gate).
    'access_gate_token' => '',

    'cookie_secure' => 'auto',            // keep 'auto' (Secure under HTTPS)
    'display_errors' => false,
];
