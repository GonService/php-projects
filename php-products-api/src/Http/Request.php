<?php

declare(strict_types=1);

namespace ProductsApi\Http;

/** Immutable HTTP request, built from PHP globals in production and by hand in tests. */
final readonly class Request
{
    /**
     * @param array<string, mixed>  $query
     * @param array<string, string> $headers lower-cased header names
     */
    public function __construct(
        public string $method,
        public string $path,
        public array $query = [],
        public array $headers = [],
        public string $body = '',
    ) {
    }

    public static function fromGlobals(): self
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (!is_string($value)) {
                continue;
            }
            if (str_starts_with($key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $headers[strtolower(str_replace('_', '-', $key))] = $value;
            }
        }
        if (($headers['authorization'] ?? '') === '' && is_string($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null)) {
            $headers['authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            is_string($path) && $path !== '' ? rtrim($path, '/') ?: '/' : '/',
            $_GET,
            $headers,
            (string) file_get_contents('php://input'),
        );
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function query(string $key): ?string
    {
        $value = $this->query[$key] ?? null;

        return is_string($value) ? trim($value) : null;
    }

    /**
     * Decodes a JSON object body.
     *
     * @return array<string, mixed>
     * @throws ApiException 415 for another content type, 400 for invalid JSON or a non-object
     */
    public function json(): array
    {
        $type = strtolower(trim(explode(';', $this->header('content-type') ?? '')[0]));
        if ($type !== 'application/json') {
            throw new ApiException(415, 'unsupported_media_type', 'Send the body as JSON with "Content-Type: application/json".');
        }
        try {
            $data = json_decode($this->body, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ApiException(400, 'invalid_json', 'The request body is not valid JSON: ' . $e->getMessage() . '.');
        }
        if (!is_array($data) || ($data !== [] && array_is_list($data))) {
            throw new ApiException(400, 'invalid_json', 'The request body must be a JSON object.');
        }

        return $data;
    }
}
