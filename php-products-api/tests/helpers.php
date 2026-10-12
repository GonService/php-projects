<?php

declare(strict_types=1);

use ProductsApi\App;
use ProductsApi\Domain\ApiKeys;
use ProductsApi\Http\Request;
use ProductsApi\Http\Response;
use ProductsApi\Support\Database;
use ProductsApi\Support\DemoSeeder;

/** API client over an in-memory catalog seeded with the demo data and one API key. */
final class Api
{
    public readonly PDO $pdo;
    public readonly App $app;
    public readonly string $key;

    public function __construct(bool $seed = true)
    {
        $this->pdo = Database::connect('sqlite::memory:');
        Database::migrate($this->pdo);
        if ($seed) {
            (new DemoSeeder($this->pdo))->run('2026-10-01 12:00:00');
        }
        $this->key = (new ApiKeys($this->pdo))->create('tests', '2026-10-01 12:00:00')['key'];
        $this->app = new App($this->pdo, static fn () => new DateTimeImmutable('2026-10-12 15:00:00', new DateTimeZone('UTC')));
    }

    /** @param array<string, string> $headers */
    public function request(string $method, string $path, array $query = [], mixed $json = null, array $headers = [], bool $auth = true): Response
    {
        if ($json !== null) {
            $headers += ['content-type' => 'application/json'];
        }
        if ($auth && $method !== 'GET') {
            $headers += ['x-api-key' => $this->key];
        }
        $body = is_string($json) ? $json : ($json === null ? '' : json_encode($json, JSON_THROW_ON_ERROR));

        return $this->app->handle(new Request($method, $path, $query, $headers, $body));
    }

    public function get(string $path, array $query = [], array $headers = []): Response
    {
        return $this->request('GET', $path, $query, null, $headers);
    }
}

/** @return array<string, mixed> */
function body(Response $response): array
{
    return json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
}

function assertError(Response $response, int $status, string $code): array
{
    assertSame($status, $response->status, 'status');
    $error = body($response)['error'] ?? [];
    assertSame($code, $error['code'] ?? null, 'error code');
    assertSame($status, $error['status'] ?? null, 'error.status');
    assertTrue(is_string($error['message'] ?? null), 'error.message');

    return $error;
}
