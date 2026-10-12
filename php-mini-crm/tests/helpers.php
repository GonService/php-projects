<?php

declare(strict_types=1);

use MiniCrm\App;
use MiniCrm\Domain\UserRepository;
use MiniCrm\Http\Request;
use MiniCrm\Http\Response;
use MiniCrm\Support\ArraySession;
use MiniCrm\Support\Database;

/** In-memory database with the schema and one admin user (admin@example.com / correct-horse-1). */
function freshDb(): PDO
{
    $pdo = Database::connect('sqlite::memory:');
    Database::migrate($pdo);
    (new UserRepository($pdo))->upsert('admin@example.com', 'Admin', 'correct-horse-1');

    return $pdo;
}

/** Test client: one app + session, so cookies-like state survives between requests. */
final class Client
{
    public readonly ArraySession $session;
    public readonly App $app;

    public function __construct(public readonly PDO $pdo, string $today = '2026-10-12')
    {
        $this->session = new ArraySession();
        $this->app = new App($pdo, $this->session, static fn () => new DateTimeImmutable($today . ' 14:30:00'));
    }

    /** @param array<string, string> $query */
    public function get(string $path, array $query = []): Response
    {
        return $this->app->handle(new Request('GET', $path, $query));
    }

    /** Posts with the session's CSRF token unless one is given explicitly. @param array<string, string> $body */
    public function post(string $path, array $body = [], bool $withToken = true): Response
    {
        if ($withToken && !isset($body['_csrf'])) {
            $body['_csrf'] = (string) ($this->session->get('csrf_token') ?? '');
        }

        return $this->app->handle(new Request('POST', $path, [], $body));
    }

    public function login(): self
    {
        $this->get('/login');
        $response = $this->post('/login', ['email' => 'admin@example.com', 'password' => 'correct-horse-1']);
        assertSame(303, $response->status, 'login');

        return $this;
    }
}
