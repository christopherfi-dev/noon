<?php
declare(strict_types=1);

namespace AlgebraEngine\Http;

final class Request
{
    /**
     * @param array<string,string> $query
     * @param array<string,string> $headers lower-cased names
     * @param array<string,string> $cookies
     */
    public function __construct(
        public string $method,
        public array $query,
        public array $headers,
        public array $cookies,
        public string $body,
        public bool $https,
        public string $host,
        public string $scriptDir = '/',
    ) {
    }

    public static function fromGlobals(): self
    {
        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($k, 5)))] = (string) $v;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
        }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
        $body = (string) file_get_contents('php://input', false, null, 0, 65536);
        $dir = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))), '/') . '/';
        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            array_map('strval', $_GET),
            $headers,
            array_map('strval', $_COOKIE),
            $body,
            $https,
            (string) ($_SERVER['HTTP_HOST'] ?? ''),
            $dir,
        );
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}
