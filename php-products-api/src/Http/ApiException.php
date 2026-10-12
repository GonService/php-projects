<?php

declare(strict_types=1);

namespace ProductsApi\Http;

/** Any error the API returns on purpose. Rendered by App as the standard error document. */
final class ApiException extends \RuntimeException
{
    /**
     * @param array<string, list<string>> $details field => messages (validation errors)
     * @param array<string, string>       $headers extra response headers (Allow, WWW-Authenticate...)
     */
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly array $details = [],
        public readonly array $headers = [],
    ) {
        parent::__construct($message, $status);
    }

    /** @param array<string, list<string>> $details */
    public static function validation(array $details): self
    {
        return new self(422, 'validation_failed', 'The given data was invalid.', $details);
    }

    public static function notFound(string $what): self
    {
        return new self(404, 'not_found', "{$what} not found.");
    }
}
