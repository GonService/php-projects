<?php

declare(strict_types=1);

namespace Modernized\Customers;

use DateTimeImmutable;

/** Read model of one row of the legacy "clientes" table, with English names in the code. */
final readonly class Customer
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public string $city,
        public DateTimeImmutable $createdAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['nome'],
            (string) $row['email'],
            (string) $row['cidade'],
            new DateTimeImmutable((string) $row['criado_em']),
        );
    }
}
