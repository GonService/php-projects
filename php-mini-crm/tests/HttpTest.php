<?php

declare(strict_types=1);

test('HTTP: health check is public', function (): void {
    $response = (new Client(freshDb()))->get('/healthz');
    assertSame(200, $response->status);
    assertSame("ok\n", $response->body);
});

test('HTTP: pages require login', function (): void {
    $client = new Client(freshDb());
    foreach (['/', '/customers', '/customers/new', '/customers/1'] as $path) {
        $response = $client->get($path);
        assertSame(303, $response->status, $path);
        assertSame('/login', $response->headers['Location']);
    }
});

test('HTTP: wrong password shows an error and does not log in', function (): void {
    $client = new Client(freshDb());
    $client->get('/login');
    $response = $client->post('/login', ['email' => 'admin@example.com', 'password' => 'nope']);
    assertSame(422, $response->status);
    assertContains('Invalid e-mail or password.', $response->body);
    assertSame(null, $client->session->get('user_id'));
});

test('HTTP: POST without a valid CSRF token is rejected', function (): void {
    $client = (new Client(freshDb()))->login();
    $response = $client->post('/customers', customerInput() + ['_csrf' => 'forged']);
    assertSame(419, $response->status);
    $response = $client->post('/customers', customerInput(), withToken: false);
    assertSame(419, $response->status);
    assertSame(0, (int) $client->pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn());
});

test('HTTP: login form itself is CSRF protected', function (): void {
    $client = new Client(freshDb());
    $response = $client->post('/login', ['email' => 'admin@example.com', 'password' => 'correct-horse-1', '_csrf' => 'x']);
    assertSame(419, $response->status);
});

test('HTTP: create a customer, then see it on the detail page', function (): void {
    $client = (new Client(freshDb()))->login();
    $response = $client->post('/customers', customerInput());
    assertSame(303, $response->status);
    assertSame('/customers/1', $response->headers['Location']);

    $page = $client->get('/customers/1');
    assertContains('Customer created.', $page->body);
    assertContains('11.222.333/0001-81', $page->body);
    assertContains('Padaria Pão Dourado Ltda', $page->body);
});

test('HTTP: invalid customer form is redisplayed with errors (422)', function (): void {
    $client = (new Client(freshDb()))->login();
    $response = $client->post('/customers', customerInput(['document' => '11.222.333/0001-80']));
    assertSame(422, $response->status);
    assertContains('Enter a valid CPF or CNPJ.', $response->body);
    assertContains('value="Padaria Pão Dourado Ltda"', $response->body);
});

test('HTTP: user content is HTML-escaped everywhere', function (): void {
    $client = (new Client(freshDb()))->login();
    $client->post('/customers', customerInput(['name' => '<script>alert(1)</script>']));
    $client->post('/customers/1/interactions', ['type' => 'note', 'body' => '<img src=x onerror=alert(2)>']);
    foreach (['/customers/1', '/customers', '/'] as $path) {
        $body = $client->get($path)->body;
        assertNotContains('<script>alert(1)</script>', $body, $path);
        assertNotContains('<img src=x', $body, $path);
    }
    assertContains('&lt;script&gt;alert(1)&lt;/script&gt;', $client->get('/customers')->body);
});

test('HTTP: timeline and overdue follow-up highlight', function (): void {
    $client = (new Client(freshDb(), '2026-10-12'))->login();
    $client->post('/customers', customerInput());
    $client->post('/customers/1/interactions', ['type' => 'call', 'body' => 'Asked for a delivery quote.']);
    $client->post('/customers/1/reminders', ['title' => 'Send proposal', 'due_date' => '2026-10-09']);

    $page = $client->get('/customers/1')->body;
    assertContains('Asked for a delivery quote.', $page);
    assertContains('Overdue since 09/10/2026', $page);

    $list = $client->get('/customers', ['follow_up' => 'overdue'])->body;
    assertContains('<tr class="overdue">', $list);
    assertContains('Overdue · 09/10/2026', $list);
});

test('HTTP: invalid follow-up date is refused', function (): void {
    $client = (new Client(freshDb()))->login();
    $client->post('/customers', customerInput());
    $client->post('/customers/1/reminders', ['title' => 'Bad date', 'due_date' => '2026-02-31']);
    assertSame(0, (int) $client->pdo->query('SELECT COUNT(*) FROM reminders')->fetchColumn());
    assertContains('valid date', $client->get('/customers/1')->body);
});

test('HTTP: marking a follow-up as done clears the overdue counter', function (): void {
    $client = (new Client(freshDb()))->login();
    $client->post('/customers', customerInput());
    $client->post('/customers/1/reminders', ['title' => 'Send proposal', 'due_date' => '2026-10-01']);
    assertContains('stat stat-alert', $client->get('/')->body);
    $response = $client->post('/reminders/1/done', ['back' => 'dashboard']);
    assertSame('/', $response->headers['Location']);
    assertNotContains('stat stat-alert', $client->get('/')->body);
});

test('HTTP: edit keeps the same document and unknown ids give 404', function (): void {
    $client = (new Client(freshDb()))->login();
    $client->post('/customers', customerInput());
    $response = $client->post('/customers/1', customerInput(['status' => 'inactive', 'city' => 'Esteio']));
    assertSame(303, $response->status);
    assertContains('Esteio', $client->get('/customers/1')->body);
    assertSame(404, $client->get('/customers/99')->status);
    assertSame(404, $client->get('/nope')->status);
    assertSame(405, $client->get('/logout')->status);
});

test('HTTP: logout ends the session', function (): void {
    $client = (new Client(freshDb()))->login();
    assertSame(200, $client->get('/')->status);
    $client->post('/logout');
    assertSame(303, $client->get('/')->status);
});
