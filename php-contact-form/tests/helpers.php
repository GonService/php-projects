<?php

declare(strict_types=1);

use ContactForm\App;
use ContactForm\Config;
use ContactForm\Domain\Settings;
use ContactForm\Http\Request;
use ContactForm\Http\Response;
use ContactForm\Mail\Mailer;
use ContactForm\Mail\NullMailer;
use ContactForm\Support\ArraySession;
use ContactForm\Support\Database;

function freshDb(): PDO
{
    $pdo = Database::connect('sqlite::memory:');
    Database::migrate($pdo);
    (new Settings($pdo))->setInboxPassword('inbox-secret-1');

    return $pdo;
}

function validMessage(array $overrides = []): array
{
    return $overrides + [
        'name' => 'Luciana Prates',
        'email' => 'Luciana@ColegioSaoFrancisco.edu.br',
        'phone' => '(51) 3332-1188',
        'company' => 'Colégio São Francisco',
        'topic' => 'quote',
        'message' => 'We need a quote for the electrical inspection of two buildings.',
        'consent' => 'yes',
        'website' => '',
    ];
}

/** Test client with a controllable clock: $client->advance(10) moves time forward. */
final class Client
{
    public readonly ArraySession $session;
    public readonly App $app;
    public int $time;

    public function __construct(
        public readonly PDO $pdo,
        public readonly Mailer $mailer = new NullMailer(),
        Config $config = new Config(),
    ) {
        $this->time = (new DateTimeImmutable('2026-10-12 10:00:00'))->getTimestamp();
        $this->session = new ArraySession();
        $this->app = new App($pdo, $this->session, $mailer, $config, fn () => (new DateTimeImmutable())->setTimestamp($this->time));
    }

    public function advance(int $seconds): self
    {
        $this->time += $seconds;

        return $this;
    }

    public function get(string $path, array $query = [], string $ip = '203.0.113.10'): Response
    {
        return $this->app->handle(new Request('GET', $path, $query, [], [], $ip));
    }

    public function post(string $path, array $body = [], string $ip = '203.0.113.10'): Response
    {
        $body += ['_csrf' => (string) ($this->session->get('csrf_token') ?? '')];

        return $this->app->handle(new Request('POST', $path, [], $body, ['user-agent' => 'TestBrowser/1.0'], $ip));
    }

    /** Loads the form, waits like a person would, then submits. */
    public function submit(array $body, string $ip = '203.0.113.10'): Response
    {
        $this->get('/', [], $ip);
        $this->advance(20);

        return $this->post('/', $body, $ip);
    }

    public function count(string $table = 'messages'): int
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    }
}
