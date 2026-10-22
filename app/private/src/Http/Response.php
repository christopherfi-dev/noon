<?php
declare(strict_types=1);

namespace AlgebraEngine\Http;

use AlgebraEngine\Support\Json;

final class Response
{
    /** @var list<string> */
    public array $cookies = [];
    /** @var array<string,string> */
    public array $headers = [
        'Content-Type' => 'application/json; charset=utf-8',
        'Cache-Control' => 'no-store',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'same-origin',
        'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'",
    ];

    /** A non-JSON body (round UI v3: the downloadable HTML report); headers say what it is. */
    public ?string $raw = null;

    public function __construct(public int $status, public array $body)
    {
    }

    public static function error(int $status, string $code, string $message, array $extra = []): self
    {
        return new self($status, ['error_code' => $code, 'message' => $message] + $extra);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $k => $v) {
            header($k . ': ' . $v);
        }
        foreach ($this->cookies as $c) {
            header('Set-Cookie: ' . $c, false);
        }
        echo $this->raw ?? Json::encode($this->body);
    }
}
