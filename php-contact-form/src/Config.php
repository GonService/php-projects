<?php

declare(strict_types=1);

namespace ContactForm;

final readonly class Config
{
    public function __construct(
        public string $companyName = 'Atlas Serviços Técnicos',
        /** Where new messages are e-mailed when SMTP is configured */
        public string $mailTo = 'contato@example.com',
        public string $mailFrom = 'no-reply@example.com',
        /** Form submissions allowed per IP address in the window */
        public int $maxSubmissions = 5,
        public int $submissionWindow = 600,
        /** Faster than this (seconds between page load and submit) is treated as a bot */
        public int $minFillSeconds = 3,
        public int $maxLoginAttempts = 5,
        public int $loginWindow = 900,
        /** @var list<string> */
        public array $trustedProxies = [],
    ) {
    }

    /** @param array<string, string|false> $env usually getenv() */
    public static function fromEnv(array $env): self
    {
        $get = static fn (string $key, string $default): string => (string) (($env[$key] ?? '') ?: $default);

        return new self(
            companyName: $get('COMPANY_NAME', 'Atlas Serviços Técnicos'),
            mailTo: $get('MAIL_TO', 'contato@example.com'),
            mailFrom: $get('MAIL_FROM', 'no-reply@example.com'),
            maxSubmissions: (int) $get('RATE_LIMIT_MAX', '5'),
            submissionWindow: (int) $get('RATE_LIMIT_WINDOW', '600'),
            minFillSeconds: (int) $get('MIN_FILL_SECONDS', '3'),
            trustedProxies: array_values(array_filter(array_map('trim', explode(',', $get('TRUSTED_PROXIES', '')))))
        );
    }
}
