<?php

declare(strict_types=1);

namespace MiniCrm\Domain;

enum DocumentType: string
{
    case Cpf = 'cpf';
    case Cnpj = 'cnpj';

    public function label(): string
    {
        return strtoupper($this->value);
    }
}
