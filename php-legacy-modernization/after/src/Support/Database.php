<?php

declare(strict_types=1);

namespace Modernized\Support;

use PDO;

final class Database
{
    /** Same SQLite file the legacy page uses: APP_DB or var/app.sqlite at the project root. */
    public static function defaultPath(): string
    {
        return getenv('APP_DB') ?: dirname(__DIR__, 3) . '/var/app.sqlite';
    }

    public static function connect(string $path): PDO
    {
        $pdo = new PDO('sqlite:' . $path, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,   // errors become exceptions, never echoed SQL
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,           // real server-side prepared statements
        ]);
        $pdo->exec('PRAGMA busy_timeout = 3000');
        // Idempotent (CREATE TABLE IF NOT EXISTS), so a fresh container starts with an empty schema.
        $pdo->exec((string) file_get_contents(dirname(__DIR__, 3) . '/database/schema.sql'));

        return $pdo;
    }
}
