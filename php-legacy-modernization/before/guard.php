<?php
/*
 * ============================================================================================
 *  INSECURE EXAMPLE - DO NOT DEPLOY
 *  Everything in before/ is deliberately vulnerable (SQL injection, XSS, CSRF) for teaching.
 *  This guard refuses to run it anywhere except PHP's built-in server on localhost with
 *  LEGACY_DEMO=1, or inside the test suite.
 * ============================================================================================
 */

$allowedByTests = PHP_SAPI === 'cli' && defined('LEGACY_TEST_HARNESS');
$allowedLocally = PHP_SAPI === 'cli-server'
    && getenv('LEGACY_DEMO') === '1'
    && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);

if (!$allowedByTests && !$allowedLocally) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit("403 - Insecure teaching example.\n"
        . "Run it only on your machine: LEGACY_DEMO=1 php -S 127.0.0.1:8001 -t before\n");
}
