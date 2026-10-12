<?php

declare(strict_types=1);

use ContactForm\Config;
use ContactForm\Mail\Email;
use ContactForm\Mail\MailException;
use ContactForm\Mail\Mailer;
use ContactForm\Mail\NullMailer;

test('Form: page renders with CSRF token and honeypot', function (): void {
    $response = (new Client(freshDb()))->get('/');
    assertSame(200, $response->status);
    assertContains('name="_csrf" value="', $response->body);
    assertContains('name="website"', $response->body);
    assertContains('class="hp" aria-hidden="true"', $response->body);
});

test('Form: valid message is stored, e-mailed and redirects to thanks (PRG)', function (): void {
    $mailer = new NullMailer();
    $client = new Client(freshDb(), $mailer);
    $response = $client->submit(validMessage());
    assertSame(303, $response->status);
    assertSame('/thanks', $response->headers['Location']);
    assertSame(1, $client->count());
    assertContains('Thank you, Luciana Prates.', $client->get('/thanks')->body);

    $row = $client->pdo->query('SELECT * FROM messages')->fetch();
    assertSame('luciana@colegiosaofrancisco.edu.br', $row['email']);
    assertSame('disabled', $row['mail_status']);
    assertSame(64, strlen($row['ip_hash']));
    assertNotContains('203.0.113.10', implode('|', $row));

    assertSame(1, count($mailer->sent));
    assertSame('luciana@colegiosaofrancisco.edu.br', $mailer->sent[0]->replyTo);
    assertContains('[Contact #1] Request a quote - Luciana Prates', $mailer->sent[0]->subject);
});

test('Form: invalid input is shown again with errors and nothing is stored', function (): void {
    $client = new Client(freshDb());
    $response = $client->submit(validMessage(['email' => 'nope', 'consent' => '']));
    assertSame(422, $response->status);
    assertContains('Enter a valid e-mail address', $response->body);
    assertContains('We need your consent', $response->body);
    assertContains('value="Luciana Prates"', $response->body);
    assertSame(0, $client->count());
});

test('Form: submitted values are escaped when the form is shown again', function (): void {
    $client = new Client(freshDb());
    $response = $client->submit(validMessage(['name' => '"><script>alert(1)</script>', 'email' => 'x']));
    assertNotContains('<script>alert(1)</script>', $response->body);
    assertContains('&quot;&gt;&lt;script&gt;', $response->body);
});

test('Form: missing or forged CSRF token is rejected with 419', function (): void {
    $client = new Client(freshDb());
    $client->get('/');
    $client->advance(20);
    assertSame(419, $client->post('/', validMessage(['_csrf' => 'forged']))->status);
    $fresh = new Client(freshDb());
    assertSame(419, $fresh->post('/', validMessage())->status, 'no session');
    assertSame(0, $client->count());
});

test('Form: honeypot submissions look successful but are dropped', function (): void {
    $mailer = new NullMailer();
    $client = new Client(freshDb(), $mailer);
    $response = $client->submit(validMessage(['website' => 'https://spam.example']));
    assertSame('/thanks', $response->headers['Location']);
    assertSame(0, $client->count());
    assertSame([], $mailer->sent);
});

test('Form: submissions faster than a person can type are dropped', function (): void {
    $client = new Client(freshDb());
    $client->get('/');
    $client->advance(1);
    $response = $client->post('/', validMessage());
    assertSame(303, $response->status);
    assertSame(0, $client->count());
});

test('Form: per-IP rate limit returns 429 with Retry-After', function (): void {
    $client = new Client(freshDb(), config: new Config(maxSubmissions: 3, submissionWindow: 600));
    for ($i = 0; $i < 3; ++$i) {
        assertSame(303, $client->submit(validMessage())->status);
    }
    $blocked = $client->submit(validMessage());
    assertSame(429, $blocked->status);
    assertContains('Too many messages', $blocked->body);
    assertTrue((int) $blocked->headers['Retry-After'] > 0, 'Retry-After');
    assertSame(3, $client->count());

    assertSame(303, $client->submit(validMessage(), '198.51.100.20')->status, 'another IP is not affected');
    $client->advance(600);
    assertSame(303, $client->submit(validMessage())->status, 'allowed again after the window');
});

test('Form: a failing mail server does not lose the message', function (): void {
    $failing = new class implements Mailer {
        public function send(Email $email): void
        {
            throw new MailException('Connection refused');
        }

        public function name(): string
        {
            return 'smtp';
        }
    };
    $client = new Client(freshDb(), $failing);
    $previous = ini_set('error_log', '/dev/null');
    $response = $client->submit(validMessage());
    ini_set('error_log', (string) $previous);
    assertSame(303, $response->status);
    assertSame('failed', $client->pdo->query('SELECT mail_status FROM messages')->fetchColumn());
});

test('Form: trusted proxy header is used only from trusted addresses', function (): void {
    $_SERVER = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_FORWARDED_FOR' => '1.1.1.1, 203.0.113.9'];
    assertSame('203.0.113.9', ContactForm\Http\Request::fromGlobals(['10.0.0.5'])->ip);
    assertSame('10.0.0.5', ContactForm\Http\Request::fromGlobals([])->ip);
    $_SERVER = [];
});

test('Form: a quick but invalid submit still shows the errors to a person', function (): void {
    $client = new Client(freshDb());
    $client->get('/');
    $response = $client->post('/', ['name' => '', 'consent' => '']);
    assertSame(422, $response->status);
    assertContains('Please tell us your name.', $response->body);
});
