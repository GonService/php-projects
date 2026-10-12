<?php

declare(strict_types=1);

namespace ProductsApi\Domain;

use PDO;

/**
 * API keys are random 32-byte tokens shown once on creation. Only their SHA-256 is stored, so a
 * leaked database does not leak usable keys (high-entropy tokens do not need a slow hash).
 */
final class ApiKeys
{
    public const PREFIX = 'pk_';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array{id: int, key: string} */
    public function create(string $name, string $now): array
    {
        $key = self::PREFIX . bin2hex(random_bytes(32));
        $this->pdo->prepare('INSERT INTO api_keys (name, key_hash, created_at) VALUES (:n, :h, :t)')
            ->execute(['n' => $name, 'h' => hash('sha256', $key), 't' => $now]);

        return ['id' => (int) $this->pdo->lastInsertId(), 'key' => $key];
    }

    /** Returns the key's name when it is valid and not revoked. */
    public function verify(string $key, string $now): ?string
    {
        if (!str_starts_with($key, self::PREFIX) || strlen($key) > 200) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT id, name FROM api_keys WHERE key_hash = :h AND revoked_at IS NULL');
        $stmt->execute(['h' => hash('sha256', $key)]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        $this->pdo->prepare('UPDATE api_keys SET last_used_at = :t WHERE id = :id')->execute(['t' => $now, 'id' => $row['id']]);

        return (string) $row['name'];
    }

    public function revoke(int $id, string $now): bool
    {
        $stmt = $this->pdo->prepare('UPDATE api_keys SET revoked_at = :t WHERE id = :id AND revoked_at IS NULL');
        $stmt->execute(['t' => $now, 'id' => $id]);

        return $stmt->rowCount() === 1;
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->pdo->query('SELECT id, name, created_at, last_used_at, revoked_at FROM api_keys ORDER BY id')->fetchAll();
    }
}
