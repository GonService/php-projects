<?php

declare(strict_types=1);

use ContactForm\App;
use ContactForm\Config;
use ContactForm\Http\Request;
use ContactForm\Mail\MailerFactory;
use ContactForm\Support\Database;
use ContactForm\Support\NativeSession;

require dirname(__DIR__) . '/src/autoload.php';

$env = getenv();
$config = Config::fromEnv($env);
$pdo = Database::connect(Database::defaultDsn());
Database::migrate($pdo);

$app = new App($pdo, new NativeSession(), MailerFactory::fromEnv($env), $config);
$app->handle(Request::fromGlobals($config->trustedProxies))->send();
