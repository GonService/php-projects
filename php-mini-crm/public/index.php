<?php

declare(strict_types=1);

use MiniCrm\App;
use MiniCrm\Http\Request;
use MiniCrm\Support\Database;
use MiniCrm\Support\NativeSession;

require dirname(__DIR__) . '/src/autoload.php';

$pdo = Database::connect(Database::defaultDsn());
Database::migrate($pdo);

(new App($pdo, new NativeSession()))->handle(Request::fromGlobals())->send();
