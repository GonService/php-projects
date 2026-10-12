<?php

declare(strict_types=1);

use ProductsApi\App;
use ProductsApi\Http\Request;
use ProductsApi\Support\Database;

require dirname(__DIR__) . '/src/autoload.php';

$pdo = Database::connect(Database::defaultDsn());
Database::migrate($pdo);

(new App($pdo, debug: getenv('API_DEBUG') === '1'))->handle(Request::fromGlobals())->send();
