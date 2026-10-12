<?php

declare(strict_types=1);

/*
 * PSR-4 autoloader for the MiniCrm\ namespace, so the project runs without `composer install`.
 * composer.json declares the same mapping for anyone who prefers Composer's autoloader.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'MiniCrm\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
