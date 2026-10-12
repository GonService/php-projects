<?php

declare(strict_types=1);

use ContactForm\Domain\ContactValidator;
use ContactForm\Domain\RateLimiter;

test('Validator accepts a complete message and normalizes it', function (): void {
    $result = (new ContactValidator())->validate(validMessage(['message' => "  Line one\r\nLine two\x07  "]));
    assertSame([], $result['errors']);
    assertSame('luciana@colegiosaofrancisco.edu.br', $result['data']['email']);
    assertSame("Line one\nLine two", $result['data']['body']);
});

test('Validator requires name, e-mail, subject, message and consent', function (): void {
    $result = (new ContactValidator())->validate([]);
    assertSame(['name', 'email', 'topic', 'message', 'consent'], array_keys($result['errors']));
});

test('Validator rejects bad e-mail, phone, unknown subject and short message', function (): void {
    $result = (new ContactValidator())->validate(validMessage([
        'email' => 'luciana@', 'phone' => 'call me', 'topic' => 'admin', 'message' => 'Hi',
    ]));
    assertSame(['email', 'phone', 'topic', 'message'], array_keys($result['errors']));
});

test('Validator blocks header injection in name and e-mail', function (): void {
    $result = (new ContactValidator())->validate(validMessage([
        'name' => "Eve\nBcc: victim@example.com",
        'email' => "eve@example.com\r\nBcc: victim@example.com",
    ]));
    assertTrue(isset($result['errors']['email']), 'email');
    assertTrue(isset($result['errors']['name']), 'name');
});

test('Validator enforces maximum lengths and ignores non-string input', function (): void {
    $result = (new ContactValidator())->validate(validMessage([
        'name' => str_repeat('a', 101), 'message' => str_repeat('b', 5001), 'company' => ['array'],
    ]));
    assertSame('At most 100 characters.', $result['errors']['name']);
    assertSame('At most 5000 characters.', $result['errors']['message']);
    assertSame('', $result['data']['company']);
});

test('RateLimiter counts hits per key inside a sliding window', function (): void {
    $limiter = new RateLimiter(freshDb(), 'salt');
    $t = 1_000_000;
    for ($i = 0; $i < 3; ++$i) {
        assertTrue($limiter->allows('contact', '1.2.3.4', 3, 600, $t + $i));
        $limiter->hit('contact', '1.2.3.4', $t + $i);
    }
    assertFalse($limiter->allows('contact', '1.2.3.4', 3, 600, $t + 10));
    assertTrue($limiter->allows('contact', '5.6.7.8', 3, 600, $t + 10), 'other IP');
    assertTrue($limiter->allows('login', '1.2.3.4', 3, 600, $t + 10), 'other bucket');
    assertSame(590, $limiter->retryAfter('contact', '1.2.3.4', 3, 600, $t + 10));
    assertTrue($limiter->allows('contact', '1.2.3.4', 3, 600, $t + 601), 'window passed');
});

test('RateLimiter stores hashed keys and prunes old entries', function (): void {
    $pdo = freshDb();
    $limiter = new RateLimiter($pdo, 'salt');
    $limiter->hit('contact', '198.51.100.7', 100);
    $limiter->hit('contact', '198.51.100.7', 5000);
    $stored = (string) $pdo->query('SELECT key_hash FROM rate_limits LIMIT 1')->fetchColumn();
    assertNotContains('198.51.100.7', $stored);
    assertSame(1, $limiter->prune(1000, 5000));
});
