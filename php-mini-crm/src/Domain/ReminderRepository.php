<?php

declare(strict_types=1);

namespace MiniCrm\Domain;

use PDO;

final class ReminderRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function add(int $customerId, string $title, string $dueDate): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO reminders (customer_id, title, due_date) VALUES (:customer, :title, :due)'
        );
        $stmt->execute(['customer' => $customerId, 'title' => $title, 'due' => $dueDate]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM reminders WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function markDone(int $id, string $doneAt): void
    {
        $stmt = $this->pdo->prepare('UPDATE reminders SET done_at = :done WHERE id = :id AND done_at IS NULL');
        $stmt->execute(['done' => $doneAt, 'id' => $id]);
    }

    /** Open follow-ups across all customers, oldest due date first. @return list<array<string, mixed>> */
    public function open(string $today, ?int $limit = null): array
    {
        $sql = 'SELECT r.*, c.name AS customer_name, (r.due_date < :today) AS overdue
                FROM reminders r JOIN customers c ON c.id = r.customer_id
                WHERE r.done_at IS NULL ORDER BY r.due_date, r.id'
            . ($limit !== null ? ' LIMIT ' . max(1, $limit) : '');
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['today' => $today]);

        return $stmt->fetchAll();
    }

    /** Open first (by due date), then done ones. @return list<array<string, mixed>> */
    public function forCustomer(int $customerId, string $today): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.*, (r.done_at IS NULL AND r.due_date < :today) AS overdue FROM reminders r
             WHERE r.customer_id = :id ORDER BY r.done_at IS NOT NULL, r.due_date, r.id'
        );
        $stmt->execute(['id' => $customerId, 'today' => $today]);

        return $stmt->fetchAll();
    }

    /** @return array{open: int, overdue: int, today: int} */
    public function counts(string $today): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) AS open,
                    COALESCE(SUM(due_date < :t1), 0) AS overdue,
                    COALESCE(SUM(due_date = :t2), 0) AS today
             FROM reminders WHERE done_at IS NULL'
        );
        $stmt->execute(['t1' => $today, 't2' => $today]);
        $row = $stmt->fetch();

        return ['open' => (int) $row['open'], 'overdue' => (int) $row['overdue'], 'today' => (int) $row['today']];
    }
}
