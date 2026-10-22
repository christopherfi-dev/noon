<?php
declare(strict_types=1);

namespace AlgebraEngine\Provider;

interface HttpTransport
{
    /**
     * @param array<string,string> $headers
     * @return array{status:int,body:string,error:?string,diagnostics?:array{curl_errno:int,stage:string,elapsed_ms:int}} error is TIMEOUT | NETWORK | TOO_LARGE or null; diagnostics accompany TIMEOUT and NETWORK
     */
    public function post(string $url, array $headers, string $body, int $connectTimeout, int $timeout, int $maxBytes): array;
}
