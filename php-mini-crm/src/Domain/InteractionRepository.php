<?php

declare(strict_types=1);

namespace MiniCrm\Domain;

use PDO;

final class InteractionRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function add(int $customerId, ?int $userId, InteractionType $type, string $body, string $createdAt): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO interactions (customer_id, user_id, type, body, created_at)
             VALUES (:customer, :user, :type, :body, :created)'
        );
        $stmt->execute([
            'customer' => $customerId,
            'user' => $userId,
            'type' => $type->value,
            'body' => $body,
            'created' => $createdAt,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** Newest first, with the author's name. @return list<array<string, mixed>> */
    public function forCustomer(int $customerId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT i.*, u.name AS author FROM interactions i
             LEFT JOIN users u ON u.id = i.user_id
             WHERE i.customer_id = :id ORDER BY i.created_at DESC, i.id DESC'
        );
        $stmt->execute(['id' => $customerId]);

        return $stmt->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function recent(int $limit = 6): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT i.*, c.name AS customer_name FROM interactions i
             JOIN customers c ON c.id = i.customer_id
             ORDER BY i.created_at DESC, i.id DESC LIMIT :limit'
        );
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
