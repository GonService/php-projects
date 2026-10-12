<?php

declare(strict_types=1);

use Modernized\App;
use Modernized\Http\Request;
use Modernized\Support\Database;
use Modernized\Support\NativeSession;

require dirname(__DIR__) . '/src/autoload.php';

$pdo = Database::connect(Database::defaultPath());

(new App($pdo, new NativeSession()))->handle(Request::fromGlobals())->send();
