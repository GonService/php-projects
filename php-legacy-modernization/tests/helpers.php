<?php

declare(strict_types=1);

use Modernized\App;
use Modernized\Http\Request;
use Modernized\Http\Response;
use Modernized\Support\ArraySession;
use Modernized\Support\Database;

/** A throwaway SQLite file with the shared schema and demo data. Deleted at shutdown. */
function tempDatabase(): string
{
    $path = tempnam(sys_get_temp_dir(), 'legacy-test-');
    $pdo = new PDO('sqlite:' . $path, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $root = dirname(__DIR__);
    $pdo->exec((string) file_get_contents($root . '/database/schema.sql'));
    $pdo->exec((string) file_get_contents($root . '/database/seed.sql'));
    register_shutdown_function(static fn () => @unlink($path));

    return $path;
}

function customerCount(string $dbPath): int
{
    return (int) (new PDO('sqlite:' . $dbPath))->query('SELECT COUNT(*) FROM clientes')->fetchColumn();
}

/** Runs the legacy page in a subprocess and returns its HTML. */
function legacyRequest(string $dbPath, array $get = [], array $post = []): string
{
    $payload = json_encode(['db' => $dbPath, 'get' => $get, 'post' => $post], JSON_THROW_ON_ERROR);
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/legacy_harness.php') . ' ' . escapeshellarg($payload);

    return (string) shell_exec($cmd . ' 2>&1');
}

/** Client for the refactored app, keeping one session between requests. */
final class Client
{
    public readonly ArraySession $session;
    public readonly App $app;
    public readonly PDO $pdo;

    public function __construct(public readonly string $dbPath)
    {
        $this->pdo = Database::connect($dbPath);
        $this->session = new ArraySession();
        $this->app = new App($this->pdo, $this->session, static fn () => new DateTimeImmutable('2026-10-12 10:00:00'));
    }

    public function get(string $path, array $query = []): Response
    {
        return $this->app->handle(new Request('GET', $path, $query));
    }

    /** Sends the session's CSRF token unless the body already has a _csrf value. */
    public function post(string $path, array $body = []): Response
    {
        if (!array_key_exists('_csrf', $body)) {
            $this->get('/');
            $body['_csrf'] = (string) $this->session->get('csrf_token');
        }

        return $this->app->handle(new Request('POST', $path, [], $body));
    }
}

const UNION_PAYLOAD = "zzz' UNION SELECT id, login, senha, 'leak', '2020-01-01' FROM usuarios --";
const ADMIN_MD5 = '0192023a7bbd73250516f069df18b500';
