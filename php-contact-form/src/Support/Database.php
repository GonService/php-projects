<?php

declare(strict_types=1);

namespace ContactForm\Support;

use PDO;

final class Database
{
    public static function connect(string $dsn): PDO
    {
        $pdo = new PDO($dsn, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA busy_timeout = 3000');

        return $pdo;
    }

    /** Path from CONTACT_DB, defaulting to var/contact.sqlite inside the project. */
    public static function defaultDsn(): string
    {
        return 'sqlite:' . (getenv('CONTACT_DB') ?: dirname(__DIR__, 2) . '/var/contact.sqlite');
    }

    public static function migrate(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL,
                phone TEXT NOT NULL DEFAULT '',
                company TEXT NOT NULL DEFAULT '',
                topic TEXT NOT NULL,
                body TEXT NOT NULL,
                ip_hash TEXT NOT NULL,
                user_agent TEXT NOT NULL DEFAULT '',
                mail_status TEXT NOT NULL DEFAULT 'disabled',
                created_at TEXT NOT NULL,
                read_at TEXT
            );
            CREATE INDEX IF NOT EXISTS messages_unread ON messages(read_at, created_at);
            CREATE TABLE IF NOT EXISTS rate_limits (
                bucket TEXT NOT NULL,
                key_hash TEXT NOT NULL,
                hit_at INTEGER NOT NULL
            );
            CREATE INDEX IF NOT EXISTS rate_limits_lookup ON rate_limits(bucket, key_hash, hit_at);
            CREATE TABLE IF NOT EXISTS settings (
                name TEXT PRIMARY KEY,
                value TEXT NOT NULL
            );
            SQL);
    }
}
