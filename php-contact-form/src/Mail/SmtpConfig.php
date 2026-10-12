<?php

declare(strict_types=1);

namespace ContactForm\Mail;

final readonly class SmtpConfig
{
    public function __construct(
        public string $host,
        public int $port = 587,
        /** "tls" (STARTTLS, port 587), "ssl" (implicit TLS, port 465) or "none" (local relay only) */
        public string $encryption = 'tls',
        public string $username = '',
        public string $password = '',
        public float $timeout = 10.0,
        public string $heloHost = 'localhost',
    ) {
        if (!in_array($encryption, ['tls', 'ssl', 'none'], true)) {
            throw new \InvalidArgumentException('SMTP_ENCRYPTION must be tls, ssl or none.');
        }
    }
}
