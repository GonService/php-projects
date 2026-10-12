<?php

declare(strict_types=1);

namespace ProductsApi\Http;

final class Response
{
    /** @param array<string, string> $headers */
    public function __construct(
        public readonly string $body = '',
        public readonly int $status = 200,
        public readonly array $headers = [],
    ) {
    }

    /** @param array<string, string> $headers */
    public static function json(mixed $data, int $status = 200, array $headers = []): self
    {
        $body = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

        return new self($body, $status, $headers + ['Content-Type' => 'application/json; charset=utf-8']);
    }

    public static function noContent(): self
    {
        return new self('', 204);
    }

    /** @param array<string, string> $headers */
    public function withHeaders(array $headers): self
    {
        return new self($this->body, $this->status, $headers + $this->headers);
    }

    public function send(): void
    {
        http_response_code($this->status);
        $defaults = [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-cache',
        ];
        foreach ($this->headers + $defaults as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->body;
    }
}
