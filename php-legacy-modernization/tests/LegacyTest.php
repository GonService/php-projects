<?php

declare(strict_types=1);

/*
 * These tests run the ORIGINAL legacy page (before/) against a throwaway database to prove the
 * vulnerabilities are real. AfterTest.php sends the same attacks to the refactored app.
 */

test('Legacy before/: search UNION injection leaks the users table (password hashes)', function (): void {
    $html = legacyRequest(tempDatabase(), ['busca' => UNION_PAYLOAD]);
    assertContains(ADMIN_MD5, $html);
    assertContains('<td>admin</td>', $html);
});

test("Legacy before/: \"' OR 1=1\" style payloads change the query", function (): void {
    $html = legacyRequest(tempDatabase(), ['busca' => "nothing' OR 1=1 OR nome LIKE '"]);
    assertContains('Padaria Pão Dourado Ltda', $html, 'all rows returned');
});

test('Legacy before/: delete link accepts SQL and wipes the table (and needs no CSRF token)', function (): void {
    $db = tempDatabase();
    legacyRequest($db, ['excluir' => '0 OR 1=1']);
    assertSame(0, customerCount($db));
});

test('Legacy before/: reflected XSS in the search box', function (): void {
    $html = legacyRequest(tempDatabase(), ['busca' => '<script>alert(document.cookie)</script>']);
    assertContains('<b><script>alert(document.cookie)</script></b>', $html);
});

test('Legacy before/: SQL errors are shown to the visitor with the query', function (): void {
    $html = legacyRequest(tempDatabase(), ['busca' => "'"]);
    assertContains('SQL: SELECT id, nome, email, cidade, criado_em FROM clientes', $html);
});

test('Legacy before/: refuses to run outside the local demo or the tests', function (): void {
    $out = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/before/clientes.php') . ' 2>&1');
    assertContains('403 - Insecure teaching example.', $out);
    assertNotContains('<table', $out);
});
