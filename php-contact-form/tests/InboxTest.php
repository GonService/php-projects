<?php

declare(strict_types=1);

use ContactForm\Support\DemoSeeder;

function loggedInInbox(): Client
{
    $client = new Client(freshDb());
    (new DemoSeeder($client->pdo))->run(new DateTimeImmutable('2026-10-12 10:00:00'));
    $client->get('/inbox/login');
    assertSame(303, $client->post('/inbox/login', ['password' => 'inbox-secret-1'])->status);

    return $client;
}

test('Inbox: requires the password', function (): void {
    $client = new Client(freshDb());
    foreach (['/inbox', '/inbox/1'] as $path) {
        assertSame('/inbox/login', $client->get($path)->headers['Location'] ?? null, $path);
    }
    $client->get('/inbox/login');
    $wrong = $client->post('/inbox/login', ['password' => 'guess']);
    assertSame(422, $wrong->status);
    assertContains('Wrong password.', $wrong->body);
    assertSame(303, $client->get('/inbox')->status);
});

test('Inbox: login attempts are rate limited', function (): void {
    $client = new Client(freshDb());
    $client->get('/inbox/login');
    for ($i = 0; $i < 5; ++$i) {
        $client->post('/inbox/login', ['password' => 'guess-' . $i]);
    }
    $blocked = $client->post('/inbox/login', ['password' => 'inbox-secret-1']);
    assertSame(429, $blocked->status, 'even the right password waits');
});

test('Inbox: lists messages with unread filter', function (): void {
    $client = loggedInInbox();
    $all = $client->get('/inbox')->body;
    assertContains('Rafael Moreira', $all);
    assertContains('All <span class="count">5</span>', $all);
    assertContains('Unread <span class="count">3</span>', $all);
    $unread = $client->get('/inbox', ['show' => 'unread'])->body;
    assertNotContains('Rafael Moreira', $unread);
    assertContains('Carlos Eduardo Vieira', $unread);
});

test('Inbox: opening a message marks it read; it can be marked unread again', function (): void {
    $client = loggedInInbox();
    $page = $client->get('/inbox/4')->body;
    assertContains('Luciana Prates', $page);
    assertContains('Unread <span class="count">2</span>', $page);
    assertSame(303, $client->post('/inbox/4/unread')->status);
    assertContains('Unread <span class="count">3</span>', $client->get('/inbox')->body);
});

test('Inbox: delete removes the message, unknown ids give 404', function (): void {
    $client = loggedInInbox();
    $client->post('/inbox/1/delete');
    assertSame(4, $client->count());
    assertSame(404, $client->get('/inbox/1')->status);
});

test('Inbox: actions need a CSRF token', function (): void {
    $client = loggedInInbox();
    assertSame(419, $client->post('/inbox/1/delete', ['_csrf' => 'x'])->status);
    assertSame(5, $client->count());
});

test('Inbox: message content from visitors is escaped', function (): void {
    $client = new Client(freshDb());
    $client->submit(validMessage(['company' => '<b onmouseover=alert(1)>ACME</b>', 'message' => '<script>steal()</script> please call me']));
    $client->get('/inbox/login');
    $client->post('/inbox/login', ['password' => 'inbox-secret-1']);
    $body = $client->get('/inbox/1')->body;
    assertNotContains('<script>steal()', $body);
    assertNotContains('<b onmouseover', $body);
    assertContains('&lt;script&gt;steal()&lt;/script&gt;', $body);
});

test('Inbox: logout closes access', function (): void {
    $client = loggedInInbox();
    $client->post('/inbox/logout');
    assertSame(303, $client->get('/inbox')->status);
});
