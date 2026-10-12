<?php

declare(strict_types=1);

namespace Modernized\Customers;

/** Validated input for a new customer, produced by CustomerService::validate(). */
final readonly class NewCustomer
{
    public function __construct(
        public string $name,
        public string $email,
        public string $city,
    ) {
    }
}
