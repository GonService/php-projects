<?php

declare(strict_types=1);

use MiniCrm\Domain\CustomerFilter;
use MiniCrm\Domain\CustomerRepository;
use MiniCrm\Domain\CustomerStatus;
use MiniCrm\Domain\CustomerValidator;
use MiniCrm\Domain\ReminderRepository;
use MiniCrm\Domain\UserRepository;
use MiniCrm\Support\DemoSeeder;

function customerInput(array $overrides = []): array
{
    return $overrides + [
        'name' => 'Padaria Pão Dourado Ltda',
        'document' => '11.222.333/0001-81',
        'contact_name' => 'Márcia Teixeira',
        'email' => 'Marcia@PaoDourado.com.br',
        'phone' => '(51) 3412-8890',
        'city' => 'Canoas',
        'status' => 'active',
    ];
}

test('Validator normalizes the document and e-mail', function (): void {
    $result = (new CustomerValidator(new CustomerRepository(freshDb())))->validate(customerInput());
    assertSame([], $result['errors']);
    assertSame('11222333000181', $result['data']['document']);
    assertSame('marcia@paodourado.com.br', $result['data']['email']);
});

test('Validator reports every invalid field', function (): void {
    $result = (new CustomerValidator(new CustomerRepository(freshDb())))->validate(customerInput([
        'name' => '', 'document' => '123', 'email' => 'not-an-email', 'phone' => 'call me', 'status' => 'vip',
    ]));
    assertSame(['name', 'document', 'email', 'phone', 'status'], array_keys($result['errors']));
});

test('Validator rejects a duplicated document but allows the same customer to keep it', function (): void {
    $repo = new CustomerRepository(freshDb());
    $validator = new CustomerValidator($repo);
    $id = $repo->create($validator->validate(customerInput())['data']);

    $dup = $validator->validate(customerInput(['name' => 'Other']));
    assertSame('Another customer already uses this document.', $dup['errors']['document'] ?? null);
    assertSame([], $validator->validate(customerInput(), $id)['errors']);
});

test('Search filters by text, document digits, status and overdue follow-ups', function (): void {
    $pdo = freshDb();
    (new DemoSeeder($pdo))->run(new DateTimeImmutable('2026-10-12'));
    $repo = new CustomerRepository($pdo);
    $names = static fn (array $rows): array => array_column($rows, 'name');

    assertSame(['Padaria Pão Dourado Ltda'], $names($repo->search(new CustomerFilter('padaria'), '2026-10-12')));
    assertSame(['Ana Paula Ribeiro'], $names($repo->search(new CustomerFilter('529.982'), '2026-10-12')));
    assertSame(['Agropecuária Campos Verdes'], $names($repo->search(new CustomerFilter('', CustomerStatus::Inactive), '2026-10-12')));
    assertSame(
        ['Ana Paula Ribeiro', 'Padaria Pão Dourado Ltda', 'Transportes Gaúcho Express'],
        $names($repo->search(new CustomerFilter('', null, 'overdue'), '2026-10-12')),
    );
});

test('Search treats LIKE wildcards and quotes as plain text', function (): void {
    $pdo = freshDb();
    (new DemoSeeder($pdo))->run(new DateTimeImmutable('2026-10-12'));
    $repo = new CustomerRepository($pdo);
    assertSame([], $repo->search(new CustomerFilter('%'), '2026-10-12'));
    assertSame([], $repo->search(new CustomerFilter("' OR '1'='1"), '2026-10-12'));
});

test('Reminder counts split overdue, today and open', function (): void {
    $pdo = freshDb();
    (new DemoSeeder($pdo))->run(new DateTimeImmutable('2026-10-12'));
    $reminders = new ReminderRepository($pdo);
    assertSame(['open' => 7, 'overdue' => 3, 'today' => 1], $reminders->counts('2026-10-12'));

    $first = $reminders->open('2026-10-12', 1)[0];
    $reminders->markDone((int) $first['id'], '2026-10-12 15:00:00');
    assertSame(['open' => 6, 'overdue' => 2, 'today' => 1], $reminders->counts('2026-10-12'));
});

test('Demo seeder is idempotent', function (): void {
    $pdo = freshDb();
    assertSame(8, (new DemoSeeder($pdo))->run(new DateTimeImmutable('2026-10-12')));
    assertSame(0, (new DemoSeeder($pdo))->run(new DateTimeImmutable('2026-10-12')));
});

test('Users: passwords are hashed and authentication is checked', function (): void {
    $pdo = freshDb();
    $users = new UserRepository($pdo);
    $hash = (string) $pdo->query("SELECT password_hash FROM users")->fetchColumn();
    assertTrue(str_starts_with($hash, '$2y$') || str_starts_with($hash, '$argon2'), 'hashed');
    assertNotContains('correct-horse-1', $hash);
    assertSame('Admin', $users->authenticate('ADMIN@example.com ', 'correct-horse-1')['name'] ?? null);
    assertSame(null, $users->authenticate('admin@example.com', 'wrong'));
    assertSame(null, $users->authenticate('nobody@example.com', 'correct-horse-1'));
});
