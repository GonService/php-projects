<?php

declare(strict_types=1);

namespace Modernized\Http;

/** Immutable HTTP request, built from PHP globals in production and by hand in tests. */
final readonly class Request
{
    /**
     * @param array<string, mixed>  $query
     * @param array<string, mixed>  $body
     * @param array<string, string> $headers lower-cased header names
     */
    public function __construct(
        public string $method,
        public string $path,
        public array $query = [],
        public array $body = [],
        public array $headers = [],
        public string $ip = '127.0.0.1',
    ) {
    }

    public static function fromGlobals(): self
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (is_string($value) && str_starts_with($key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;
            }
        }
        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            is_string($path) && $path !== '' ? $path : '/',
            $_GET,
            $_POST,
            $headers,
            (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
        );
    }

    public function input(string $key, string $default = ''): string
    {
        $value = $this->body[$key] ?? $default;

        return is_string($value) ? trim($value) : $default;
    }

    public function query(string $key, string $default = ''): string
    {
        $value = $this->query[$key] ?? $default;

        return is_string($value) ? trim($value) : $default;
    }
}
