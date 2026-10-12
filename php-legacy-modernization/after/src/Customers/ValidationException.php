<?php

declare(strict_types=1);

namespace Modernized\Customers;

final class ValidationException extends \DomainException
{
    /**
     * @param array<string, string> $errors field => message
     * @param array<string, string> $input  cleaned values to show again in the form
     */
    public function __construct(public readonly array $errors, public readonly array $input)
    {
        parent::__construct('Invalid customer data.');
    }
}
