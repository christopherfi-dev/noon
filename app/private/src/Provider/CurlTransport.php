<?php
declare(strict_types=1);

namespace AlgebraEngine\Provider;

/** HTTPS-only cURL transport with certificate verification and a response size cap. */
final class CurlTransport implements HttpTransport
{
    public function post(string $url, array $headers, string $body, int $connectTimeout, int $timeout, int $maxBytes): array
    {
        if (!str_starts_with($url, 'https://')) {
            return ['status' => 0, 'body' => '', 'error' => 'NETWORK'];
        }
        $buffer = '';
        $tooLarge = false;
        $ch = curl_init($url);
        $lines = [];
        foreach ($headers as $k => $v) {
            $lines[] = $k . ': ' . $v;
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_WRITEFUNCTION => function ($ch, string $chunk) use (&$buffer, &$tooLarge, $maxBytes): int {
                if (strlen($buffer) + strlen($chunk) > $maxBytes) {
                    $tooLarge = true;
                    return 0;
                }
                $buffer .= $chunk;
                return strlen($chunk);
            },
        ]);
        $ok = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $errno = curl_errno($ch);
        // Round 4 ruling 4: how far a failed request got. Zero times mean the phase never completed.
        $diagnostics = [
            'curl_errno' => $errno,
            'stage' => curl_getinfo($ch, CURLINFO_CONNECT_TIME) <= 0 ? 'connect' : (curl_getinfo($ch, CURLINFO_APPCONNECT_TIME) <= 0 ? 'tls' : 'response'),
            'elapsed_ms' => (int) round(curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000),
        ];
        curl_close($ch);
        if ($tooLarge) {
            return ['status' => $status, 'body' => '', 'error' => 'TOO_LARGE'];
        }
        if ($ok === false) {
            return ['status' => $status, 'body' => '', 'error' => $errno === CURLE_OPERATION_TIMEDOUT ? 'TIMEOUT' : 'NETWORK', 'diagnostics' => $diagnostics];
        }
        return ['status' => $status, 'body' => $buffer, 'error' => null];
    }
}
