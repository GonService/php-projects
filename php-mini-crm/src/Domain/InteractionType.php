<?php

declare(strict_types=1);

namespace MiniCrm\Domain;

enum InteractionType: string
{
    case Call = 'call';
    case Email = 'email';
    case Meeting = 'meeting';
    case WhatsApp = 'whatsapp';
    case Note = 'note';

    public function label(): string
    {
        return match ($this) {
            self::Call => 'Phone call',
            self::Email => 'E-mail',
            self::Meeting => 'Meeting',
            self::WhatsApp => 'WhatsApp',
            self::Note => 'Note',
        };
    }
}
