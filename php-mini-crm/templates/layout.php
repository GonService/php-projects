<?php
/** @var \MiniCrm\Support\View $this */
/** @var string $content */
/** @var string $path */
/** @var array<string, mixed> $user */
/** @var array{message: string, kind: string}|null $flash */
$active = static fn (string $prefix): string => ($prefix === '/' ? $path === '/' : str_starts_with($path, $prefix)) ? ' class="active"' : '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mini CRM</title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<header class="topbar">
    <div class="wrap topbar-inner">
        <a class="brand" href="/"><span class="brand-mark">M</span> Mini CRM</a>
        <nav>
            <a href="/"<?= $active('/') ?>>Dashboard</a>
            <a href="/customers"<?= $path !== '/customers/new' ? $active('/customers') : '' ?>>Customers</a>
            <a href="/customers/new"<?= $active('/customers/new') ?>>New customer</a>
        </nav>
        <form method="post" action="/logout" class="user">
            <?= $this->csrf() ?>
            <span><?= $this->e($user['name']) ?></span>
            <button type="submit" class="link">Sign out</button>
        </form>
    </div>
</header>
<main class="wrap">
    <?php if ($flash !== null): ?>
        <div class="flash flash-<?= $this->e($flash['kind']) ?>" role="status"><?= $this->e($flash['message']) ?></div>
    <?php endif ?>
    <?= $content ?>
</main>
<footer class="wrap footer">Mini CRM · PHP 8.3 + SQLite</footer>
</body>
</html>
