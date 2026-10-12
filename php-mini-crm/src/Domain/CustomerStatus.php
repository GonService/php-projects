<?php

declare(strict_types=1);

namespace MiniCrm\Domain;

enum CustomerStatus: string
{
    case Lead = 'lead';
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
