<?php
declare(strict_types=1);

/*
 * Smoke test of the built upload bundle in its deployment layout
 * (public_html/algebra + algebra-private side by side), mock mode, using
 * PHP's built-in server. Not a substitute for the on-host smoke test.
 *   php app/tools/bundle_smoke.php dist/stage
 */
$stage = realpath($argv[1] ?? (dirname(__DIR__, 2) . '/dist/stage'));
$tmp = sys_get_temp_dir() . '/ae_bundle_' . bin2hex(random_bytes(4));
mkdir($tmp);
file_put_contents("$tmp/config.php", "<?php return ['db_path' => '$tmp/db.sqlite'];");
$port = random_int(41000, 49000);
$proc = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", '-t', "$stage/public_html"], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, null, ['ALGEBRA_CONFIG' => "$tmp/config.php", 'PATH' => getenv('PATH')]);
usleep(600000);
$jar = "$tmp/cookies";
$fail = 0;
function req(string $method, string $url, ?array $body, array $headers, string $jar): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $headers)]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $out = (string) curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$code, json_decode($out, true), $out];
}
$check = function (string $name, bool $ok, string $detail = '') use (&$fail) {
    $fail += $ok ? 0 : 1;
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . ($detail !== '' ? "  ($detail)" : '') . PHP_EOL;
};
$base = "http://127.0.0.1:$port/algebra";
try {
    [$c, , $raw] = req('GET', "$base/", null, [], $jar);
    $check('index.html served', $c === 200 && str_contains($raw, 'Algebra Engine'));
    [$c, $b] = req('GET', "$base/api.php?action=bootstrap", null, [], $jar);
    $check('bootstrap via bundled api.php finds ../../algebra-private', $c === 200 && $b['provider_mode'] === 'mock' && count($b['catalog']) === 205, "HTTP $c");
    $csrf = $b['csrf_token'] ?? '';
    [$c, $v] = req('POST', "$base/api.php?action=start", ['idempotency_key' => bin2hex(random_bytes(10)), 'expected_revision' => 0, 'entry_route' => 'TOPIC', 'target_skill_id' => 'Q07', 'task_token' => null, 'replace_current' => false], ["X-CSRF-Token: $csrf"], $jar);
    $check('start Q07 presents a host-accepted item', $c === 200 && ($v['active_item']['response_type'] ?? '') === 'scalar', "HTTP $c");
    [$c, $v2] = req('POST', "$base/api.php?action=event", ['idempotency_key' => bin2hex(random_bytes(10)), 'expected_revision' => $v['revision'], 'action' => 'REQUEST_HINT', 'item_id' => $v['active_item']['item_id'], 'item_version' => 1, 'payload' => new stdClass()], ["X-CSRF-Token: $csrf"], $jar);
    $check('hint commits support', $c === 200 && $v2['active_item']['support_status'] === 'ASSISTED', "HTTP $c");
    [$c, $v3] = req('POST', "$base/api.php?action=event", ['idempotency_key' => bin2hex(random_bytes(10)), 'expected_revision' => $v2['revision'], 'action' => 'END_SESSION', 'item_id' => null, 'item_version' => null, 'payload' => new stdClass()], ["X-CSRF-Token: $csrf"], $jar);
    $check('end session', $c === 200 && $v3['status'] === 'CLOSED', "HTTP $c");
    [$c, $r] = req('GET', "$base/api.php?action=report", null, [], $jar);
    $check('report is labelled mock', $c === 200 && str_starts_with($r['provider_label'], 'MOCK'), "HTTP $c");
    [$c] = req('POST', "$base/api.php?action=event", ['idempotency_key' => bin2hex(random_bytes(10)), 'expected_revision' => 0, 'action' => 'END_SESSION', 'item_id' => null, 'item_version' => null, 'payload' => new stdClass()], [], $jar);
    $check('mutation without CSRF refused', $c === 403, "HTTP $c");
    foreach (['/algebra-private/config.example.php', '/algebra/../algebra-private/bootstrap.php', '/algebra/config.php', '/algebra/src/App.php', '/algebra-private/var/README.txt'] as $p) {
        [$c, , $raw] = req('GET', "http://127.0.0.1:$port$p", null, [], $jar);
        $check("private path not served: $p", $c === 404 && !str_contains($raw, '<?php'), "HTTP $c");
    }
} finally {
    proc_terminate($proc);
    proc_close($proc);
    foreach (glob("$tmp/*") as $f) {
        unlink($f);
    }
    rmdir($tmp);
}
echo $fail === 0 ? "BUNDLE SMOKE PASSED\n" : "BUNDLE SMOKE FAILED ($fail)\n";
exit($fail === 0 ? 0 : 1);
