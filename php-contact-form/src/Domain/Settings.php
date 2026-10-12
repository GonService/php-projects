<?php

declare(strict_types=1);

namespace ContactForm\Domain;

use PDO;

/** Key/value settings: the inbox password hash and the secret used to hash IP addresses. */
final class Settings
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(string $name): ?string
    {
        $stmt = $this->pdo->prepare('SELECT value FROM settings WHERE name = :n');
        $stmt->execute(['n' => $name]);
        $value = $stmt->fetchColumn();

        return $value === false ? null : (string) $value;
    }

    public function set(string $name, string $value): void
    {
        $this->pdo->prepare(
            'INSERT INTO settings (name, value) VALUES (:n, :v) ON CONFLICT(name) DO UPDATE SET value = excluded.value'
        )->execute(['n' => $name, 'v' => $value]);
    }

    /** Random per-installation secret, created on first use. */
    public function secret(): string
    {
        $secret = $this->get('app_secret');
        if ($secret === null) {
            $secret = bin2hex(random_bytes(32));
            $this->set('app_secret', $secret);
        }

        return $secret;
    }

    public function setInboxPassword(string $password): void
    {
        $this->set('inbox_password_hash', password_hash($password, PASSWORD_DEFAULT));
    }

    public function verifyInboxPassword(string $password): bool
    {
        $hash = $this->get('inbox_password_hash');

        return $hash !== null && password_verify($password, $hash);
    }
}
