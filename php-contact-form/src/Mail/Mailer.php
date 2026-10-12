<?php

declare(strict_types=1);

namespace ContactForm\Mail;

interface Mailer
{
    /** @throws MailException when the message could not be handed to the mail server */
    public function send(Email $email): void;

    /** Short name stored with each message: "disabled", "smtp"... */
    public function name(): string;
}
