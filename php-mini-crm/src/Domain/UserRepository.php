<?php

declare(strict_types=1);

namespace MiniCrm\Domain;

use PDO;

final class UserRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** Creates the user, or resets the password if the e-mail already exists. */
    public function upsert(string $email, string $name, string $password): int
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (email, name, password_hash) VALUES (:email, :name, :hash)
             ON CONFLICT(email) DO UPDATE SET name = excluded.name, password_hash = excluded.password_hash'
        );
        $stmt->execute(['email' => mb_strtolower($email), 'name' => $name, 'hash' => $hash]);

        return (int) $this->findByEmail($email)['id'];
    }

    /** @return array<string, mixed>|null */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute(['email' => mb_strtolower(trim($email))]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, email, name FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** Returns the user on success. Verifies against a dummy hash when the user is unknown. */
    public function authenticate(string $email, string $password): ?array
    {
        $user = $this->findByEmail($email);
        $hash = $user['password_hash'] ?? '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
        if (!password_verify($password, (string) $hash) || $user === null) {
            return null;
        }
        if (password_needs_rehash((string) $hash, PASSWORD_DEFAULT)) {
            $this->pdo->prepare('UPDATE users SET password_hash = :h WHERE id = :id')
                ->execute(['h' => password_hash($password, PASSWORD_DEFAULT), 'id' => $user['id']]);
        }

        return $user;
    }
}
