<?php

declare(strict_types=1);

namespace MiniCrm\Domain;

use PDO;

final class CustomerRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Search by name, contact, e-mail, city or document, filtered by status and follow-up state.
     *
     * @return list<array<string, mixed>>
     */
    public function search(CustomerFilter $filter, string $today): array
    {
        $where = [];
        $params = ['today' => $today];

        if ($filter->query !== '') {
            $like = '%' . addcslashes($filter->query, '%_\\') . '%';
            $digits = preg_replace('/\W/', '', $filter->query) ?? '';
            $where[] = "(c.name LIKE :q ESCAPE '\\' OR c.contact_name LIKE :q ESCAPE '\\'"
                . " OR c.email LIKE :q ESCAPE '\\' OR c.city LIKE :q ESCAPE '\\'"
                . ($digits !== '' ? " OR c.document LIKE :doc ESCAPE '\\'" : '') . ')';
            $params['q'] = $like;
            if ($digits !== '') {
                $params['doc'] = '%' . strtoupper($digits) . '%';
            }
        }
        if ($filter->status !== null) {
            $where[] = 'c.status = :status';
            $params['status'] = $filter->status->value;
        }
        if ($filter->followUp === 'overdue') {
            $where[] = 'r.next_due < :today';
        } elseif ($filter->followUp === 'open') {
            $where[] = 'r.next_due IS NOT NULL';
        }

        $sql = 'SELECT c.*, r.next_due, r.open_count, (r.next_due < :today) AS overdue
                FROM customers c
                LEFT JOIN (
                    SELECT customer_id, MIN(due_date) AS next_due, COUNT(*) AS open_count
                    FROM reminders WHERE done_at IS NULL GROUP BY customer_id
                ) r ON r.customer_id = c.id'
            . ($where !== [] ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY c.name COLLATE NOCASE LIMIT 500';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM customers WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function documentTaken(string $document, ?int $exceptId = null): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM customers WHERE document = :doc AND id IS NOT :id');
        $stmt->execute(['doc' => $document, 'id' => $exceptId]);

        return $stmt->fetchColumn() !== false;
    }

    /** @param array<string, string> $data validated data from CustomerValidator */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO customers (name, document, contact_name, email, phone, city, status)
             VALUES (:name, :document, :contact_name, :email, :phone, :city, :status)'
        );
        $stmt->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, string> $data */
    public function update(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE customers SET name = :name, document = :document, contact_name = :contact_name,
                email = :email, phone = :phone, city = :city, status = :status
             WHERE id = :id'
        );
        $stmt->execute($data + ['id' => $id]);
    }

    /** @return array<string, int> */
    public function countByStatus(): array
    {
        $counts = array_fill_keys(array_map(static fn (CustomerStatus $s) => $s->value, CustomerStatus::cases()), 0);
        foreach ($this->pdo->query('SELECT status, COUNT(*) AS n FROM customers GROUP BY status') as $row) {
            $counts[$row['status']] = (int) $row['n'];
        }

        return $counts;
    }
}
