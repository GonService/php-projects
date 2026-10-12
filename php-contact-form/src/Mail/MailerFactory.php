<?php

declare(strict_types=1);

namespace ContactForm\Mail;

final class MailerFactory
{
    /**
     * SMTP is optional: without SMTP_HOST every message is only stored in the inbox.
     *
     * @param array<string, string|false> $env usually getenv()
     */
    public static function fromEnv(array $env): Mailer
    {
        $host = (string) ($env['SMTP_HOST'] ?? '');
        if ($host === '') {
            return new NullMailer();
        }
        $encryption = (string) ($env['SMTP_ENCRYPTION'] ?? '') ?: 'tls';

        return new SmtpMailer(new SmtpConfig(
            host: $host,
            port: (int) (($env['SMTP_PORT'] ?? '') ?: ($encryption === 'ssl' ? 465 : 587)),
            encryption: $encryption,
            username: (string) ($env['SMTP_USERNAME'] ?? ''),
            password: (string) ($env['SMTP_PASSWORD'] ?? ''),
            heloHost: (string) (($env['SMTP_HELO'] ?? '') ?: 'localhost'),
        ));
    }
}
