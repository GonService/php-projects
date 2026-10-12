<?php

/*
 * Runs before/clientes.php once, in a separate process, as if a browser had sent the request.
 * Used only by the tests to demonstrate the legacy vulnerabilities on a throwaway database.
 *
 *   php tests/legacy_harness.php '<json: {"db": "...", "get": {...}, "post": {...}}>'
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$request = json_decode($argv[1] ?? '{}', true, 8, JSON_THROW_ON_ERROR);
putenv('APP_DB=' . $request['db']);
$_GET = $request['get'] ?? [];
$_POST = $request['post'] ?? [];
$_SERVER['REQUEST_METHOD'] = $_POST !== [] ? 'POST' : 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

const LEGACY_TEST_HARNESS = true;
chdir(dirname(__DIR__) . '/before');
require dirname(__DIR__) . '/before/clientes.php';
