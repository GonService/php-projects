<?php

declare(strict_types=1);

namespace ProductsApi\Support;

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
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA busy_timeout = 3000');

        return $pdo;
    }

    /** Path from API_DB, defaulting to var/catalog.sqlite inside the project. */
    public static function defaultDsn(): string
    {
        return 'sqlite:' . (getenv('API_DB') ?: dirname(__DIR__, 2) . '/var/catalog.sqlite');
    }

    public static function migrate(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                slug TEXT NOT NULL UNIQUE,
                description TEXT NOT NULL DEFAULT '',
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                sku TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                description TEXT NOT NULL DEFAULT '',
                price_cents INTEGER NOT NULL CHECK (price_cents >= 0),
                stock INTEGER NOT NULL DEFAULT 0 CHECK (stock >= 0),
                active INTEGER NOT NULL DEFAULT 1,
                category_id INTEGER REFERENCES categories(id) ON DELETE RESTRICT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            );
            CREATE INDEX IF NOT EXISTS products_category ON products(category_id);
            CREATE INDEX IF NOT EXISTS products_price ON products(price_cents);
            CREATE TABLE IF NOT EXISTS api_keys (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                key_hash TEXT NOT NULL UNIQUE,
                created_at TEXT NOT NULL,
                last_used_at TEXT,
                revoked_at TEXT
            );
            SQL);
    }
}
