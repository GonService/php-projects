<?php

declare(strict_types=1);

/*
 * Minimal test runner: every tests/*Test.php file registers closures with test(), and the
 * runner executes them in isolation. Exit code 1 if anything fails, so CI can rely on it.
 *
 *   php tests/run.php            run everything
 *   php tests/run.php Document   only tests whose name contains "Document"
 */

require dirname(__DIR__) . '/src/autoload.php';

final class AssertionFailed extends Exception
{
}

/** @var array<string, Closure> $GLOBALS['tests'] */
$GLOBALS['tests'] = [];

function test(string $name, Closure $fn): void
{
    $GLOBALS['tests'][$name] = $fn;
}

function fail(string $message): never
{
    throw new AssertionFailed($message);
}

function assertSame(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        fail(($message !== '' ? $message . ': ' : '') . 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function assertTrue(bool $condition, string $message = 'expected true'): void
{
    if (!$condition) {
        fail($message);
    }
}

function assertFalse(bool $condition, string $message = 'expected false'): void
{
    assertTrue(!$condition, $message);
}

function assertContains(string $needle, string $haystack, string $message = ''): void
{
    if (!str_contains($haystack, $needle)) {
        fail(($message !== '' ? $message . ': ' : '') . 'missing ' . var_export($needle, true));
    }
}

function assertNotContains(string $needle, string $haystack, string $message = ''): void
{
    if (str_contains($haystack, $needle)) {
        fail(($message !== '' ? $message . ': ' : '') . 'unexpected ' . var_export($needle, true));
    }
}

/** @param class-string<Throwable> $class */
function assertThrows(string $class, Closure $fn): Throwable
{
    try {
        $fn();
    } catch (Throwable $e) {
        if ($e instanceof $class) {
            return $e;
        }
        fail('expected ' . $class . ', got ' . $e::class . ': ' . $e->getMessage());
    }
    fail('expected ' . $class . ' to be thrown');
}

if (is_file(__DIR__ . '/helpers.php')) {
    require __DIR__ . '/helpers.php';
}
foreach (glob(__DIR__ . '/*Test.php') ?: [] as $file) {
    require $file;
}

$filter = $argv[1] ?? '';
[$green, $red, $reset] = stream_isatty(STDOUT) ? ["\033[32m", "\033[31m", "\033[0m"] : ['', '', ''];
$passed = $failed = 0;
foreach ($GLOBALS['tests'] as $name => $fn) {
    if ($filter !== '' && !str_contains($name, $filter)) {
        continue;
    }
    try {
        $fn();
        ++$passed;
        echo "  {$green}ok{$reset}   {$name}\n";
    } catch (Throwable $e) {
        ++$failed;
        $where = $e instanceof AssertionFailed ? '' : ' (' . $e::class . ' at ' . basename($e->getFile()) . ':' . $e->getLine() . ')';
        echo "  {$red}FAIL{$reset} {$name}\n       {$e->getMessage()}{$where}\n";
    }
}

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 && $passed > 0 ? 0 : 1);
