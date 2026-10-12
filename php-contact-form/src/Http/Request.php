<?php

declare(strict_types=1);

namespace ContactForm\Http;

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

    /**
     * @param list<string> $trustedProxies REMOTE_ADDR values allowed to set X-Forwarded-For
     *                                     (e.g. the load balancer in front of the app)
     */
    public static function fromGlobals(array $trustedProxies = []): self
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (is_string($value) && str_starts_with($key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;
            }
        }
        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        if (in_array($ip, $trustedProxies, true) && isset($headers['x-forwarded-for'])) {
            // The proxy appends the address it saw; take the right-most entry it added.
            $forwarded = array_map('trim', explode(',', $headers['x-forwarded-for']));
            $candidate = end($forwarded);
            $ip = filter_var($candidate, FILTER_VALIDATE_IP) !== false ? $candidate : $ip;
        }

        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            is_string($path) && $path !== '' ? $path : '/',
            $_GET,
            $_POST,
            $headers,
            $ip,
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
