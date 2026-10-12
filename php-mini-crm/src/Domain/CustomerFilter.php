<?php

declare(strict_types=1);

namespace MiniCrm\Domain;

final readonly class CustomerFilter
{
    public function __construct(
        public string $query = '',
        public ?CustomerStatus $status = null,
        /** '', 'open' (has open follow-ups) or 'overdue' */
        public string $followUp = '',
    ) {
    }

    /** @param array<string, mixed> $query raw query string values */
    public static function fromQuery(array $query): self
    {
        $q = is_string($query['q'] ?? null) ? trim($query['q']) : '';
        $status = CustomerStatus::tryFrom(is_string($query['status'] ?? null) ? $query['status'] : '');
        $followUp = in_array($query['follow_up'] ?? '', ['open', 'overdue'], true) ? (string) $query['follow_up'] : '';

        return new self(mb_substr($q, 0, 100), $status, $followUp);
    }
}
