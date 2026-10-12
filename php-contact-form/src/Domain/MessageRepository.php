<?php

declare(strict_types=1);

namespace ContactForm\Domain;

use PDO;

final class MessageRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @param array<string, string> $data validated data from ContactValidator */
    public function save(array $data, string $ipHash, string $userAgent, string $createdAt): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO messages (name, email, phone, company, topic, body, ip_hash, user_agent, created_at)
             VALUES (:name, :email, :phone, :company, :topic, :body, :ip_hash, :ua, :created)'
        );
        $stmt->execute([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'company' => $data['company'],
            'topic' => $data['topic'],
            'body' => $data['body'],
            'ip_hash' => $ipHash,
            'ua' => mb_substr($userAgent, 0, 255),
            'created' => $createdAt,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function setMailStatus(int $id, string $status): void
    {
        $this->pdo->prepare('UPDATE messages SET mail_status = :s WHERE id = :id')->execute(['s' => $status, 'id' => $id]);
    }

    /** @return list<array<string, mixed>> newest first */
    public function list(bool $unreadOnly, int $limit = 200): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM messages' . ($unreadOnly ? ' WHERE read_at IS NULL' : '')
            . ' ORDER BY created_at DESC, id DESC LIMIT :limit'
        );
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM messages WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function markRead(int $id, string $at): void
    {
        $this->pdo->prepare('UPDATE messages SET read_at = :at WHERE id = :id AND read_at IS NULL')
            ->execute(['at' => $at, 'id' => $id]);
    }

    public function markUnread(int $id): void
    {
        $this->pdo->prepare('UPDATE messages SET read_at = NULL WHERE id = :id')->execute(['id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->pdo->prepare('DELETE FROM messages WHERE id = :id')->execute(['id' => $id]);
    }

    /** @return array{total: int, unread: int} */
    public function counts(): array
    {
        $row = $this->pdo->query('SELECT COUNT(*) AS total, COALESCE(SUM(read_at IS NULL), 0) AS unread FROM messages')->fetch();

        return ['total' => (int) $row['total'], 'unread' => (int) $row['unread']];
    }
}
