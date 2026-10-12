<?php

declare(strict_types=1);

namespace ContactForm\Domain;

enum Topic: string
{
    case Quote = 'quote';
    case Support = 'support';
    case Partnership = 'partnership';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Quote => 'Request a quote',
            self::Support => 'Support for an existing system',
            self::Partnership => 'Partnership',
            self::Other => 'Other',
        };
    }
}
