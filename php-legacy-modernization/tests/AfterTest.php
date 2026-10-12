<?php

declare(strict_types=1);

use Modernized\Customers\CustomerRepository;
use Modernized\Customers\CustomerService;
use Modernized\Customers\ValidationException;

test('After: the same UNION injection returns no rows and leaks nothing', function (): void {
    $client = new Client(tempDatabase());
    $response = $client->get('/', ['q' => UNION_PAYLOAD]);
    assertSame(200, $response->status);
    assertNotContains(ADMIN_MD5, $response->body);
    assertNotContains('<td>admin</td>', $response->body);
    assertNotContains('<td>leak</td>', $response->body);
    assertContains('No customers found.', $response->body);
});

test("After: \"' OR 1=1\" is searched as literal text", function (): void {
    $client = new Client(tempDatabase());
    assertContains('No customers found.', $client->get('/', ['q' => "nothing' OR 1=1 OR nome LIKE '"])->body);
    assertSame(8, customerCount($client->dbPath));
});

test('After: LIKE wildcards in the search are literal too', function (): void {
    $repo = new CustomerRepository((new Client(tempDatabase()))->pdo);
    assertSame([], $repo->search('%'));
    assertSame([], $repo->search('_'));
    assertSame(1, count($repo->search('Pão')));
});

test('After: injection in the add form is stored as plain text, table intact', function (): void {
    $client = new Client(tempDatabase());
    $name = "O'Brien'); DROP TABLE clientes; --";
    $response = $client->post('/customers', ['name' => $name, 'email' => 'obrien@example.com', 'city' => 'Esteio']);
    assertSame(303, $response->status);
    assertSame(9, customerCount($client->dbPath));
    $stored = $client->pdo->query("SELECT nome FROM clientes WHERE email = 'obrien@example.com'")->fetchColumn();
    assertSame($name, $stored);
});

test('After: delete is POST-only, needs the CSRF token and an integer id', function (): void {
    $client = new Client(tempDatabase());
    assertSame(405, $client->get('/customers/1/delete')->status, 'GET link no longer deletes');
    assertSame(419, $client->post('/customers/1/delete', ['_csrf' => 'forged'])->status);
    assertSame(404, $client->post('/customers/0 OR 1=1/delete')->status);
    assertSame(8, customerCount($client->dbPath));

    assertSame(303, $client->post('/customers/1/delete')->status);
    assertSame(7, customerCount($client->dbPath));
    assertSame(404, $client->post('/customers/1/delete')->status, 'already gone');
});

test('After: adding a customer needs the CSRF token', function (): void {
    $client = new Client(tempDatabase());
    $response = $client->post('/customers', ['name' => 'Cross Site', 'email' => 'x@example.com', '_csrf' => '']);
    assertSame(419, $response->status);
    assertSame(8, customerCount($client->dbPath));
});

test('After: search term and stored names are HTML-escaped (no XSS)', function (): void {
    $client = new Client(tempDatabase());
    $reflected = $client->get('/', ['q' => '<script>alert(document.cookie)</script>'])->body;
    assertNotContains('<script>alert', $reflected);
    assertContains('&lt;script&gt;alert(document.cookie)&lt;/script&gt;', $reflected);

    $client->post('/customers', ['name' => '<img src=x onerror=alert(1)>', 'email' => 'xss@example.com', 'city' => '']);
    $page = $client->get('/')->body;
    assertNotContains('<img src=x', $page);
    assertContains('&lt;img src=x onerror=alert(1)&gt;', $page);
});

test('After: invalid input is refused with messages and the values kept', function (): void {
    $client = new Client(tempDatabase());
    $response = $client->post('/customers', ['name' => 'A', 'email' => 'not-an-email', 'city' => str_repeat('x', 81)]);
    assertSame(422, $response->status);
    assertContains('Name must have between 2 and 120 characters.', $response->body);
    assertContains('Enter a valid e-mail address.', $response->body);
    assertContains('City must have at most 80 characters.', $response->body);
    assertContains('value="not-an-email"', $response->body);
    assertSame(8, customerCount($client->dbPath));
});

test('After: successful add redirects (PRG) and shows a flash message once', function (): void {
    $client = new Client(tempDatabase());
    $client->post('/customers', ['name' => '  Mercado Central  ', 'email' => 'COMPRAS@Central.com.br', 'city' => 'Esteio']);
    $page = $client->get('/')->body;
    assertContains('Customer Mercado Central added.', $page);
    assertContains('compras@central.com.br', $page);
    assertNotContains('Customer Mercado Central added.', $client->get('/')->body);
});

test('After: database errors show a generic page, never SQL', function (): void {
    $client = new Client(tempDatabase());
    $client->pdo->exec('DROP TABLE clientes');
    $previous = ini_set('error_log', '/dev/null');
    $response = $client->get('/', ['q' => "'"]);
    ini_set('error_log', (string) $previous);
    assertSame(500, $response->status);
    assertNotContains('SELECT', $response->body);
    assertNotContains('clientes', $response->body);
});

test('After: CustomerService validation normalizes input', function (): void {
    $service = new CustomerService(new CustomerRepository((new Client(tempDatabase()))->pdo), static fn () => new DateTimeImmutable());
    $customer = $service->validate(['name' => ' Ana Paula ', 'email' => ' Ana@Example.COM ', 'city' => ' Gravataí ']);
    assertSame(['Ana Paula', 'ana@example.com', 'Gravataí'], [$customer->name, $customer->email, $customer->city]);
    $e = assertThrows(ValidationException::class, fn () => $service->validate(['name' => ['array'], 'email' => 5]));
    assertSame(['name', 'email'], array_keys($e->errors));
});

test('After: insecure before/ code is never shipped in the Docker image', function (): void {
    $root = dirname(__DIR__);
    assertFalse(is_file($root . '/before/Dockerfile'), 'no Dockerfile in before/');
    assertNotContains('before', (string) file_get_contents($root . '/Dockerfile'));
    assertContains('before', (string) file_get_contents($root . '/.dockerignore'));
    assertContains('Require all denied', (string) file_get_contents($root . '/before/.htaccess'));
});
