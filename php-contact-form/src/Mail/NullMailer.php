<?php

declare(strict_types=1);

namespace ContactForm\Mail;

/** Used when SMTP is not configured and in tests: keeps the e-mails in memory, sends nothing. */
final class NullMailer implements Mailer
{
    /** @var list<Email> */
    public array $sent = [];

    public function send(Email $email): void
    {
        $this->sent[] = $email;
    }

    public function name(): string
    {
        return 'disabled';
    }
}
