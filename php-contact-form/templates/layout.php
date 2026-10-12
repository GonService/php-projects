<?php
/** @var \ContactForm\Support\View $this */
/** @var string $content */
/** @var string $companyName */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Contact · <?= $this->e($companyName) ?></title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<header class="site-header">
    <div class="wrap site-header-inner">
        <a class="logo" href="/"><?= $this->e($companyName) ?></a>
        <nav class="site-nav"><a href="/">Services</a><a href="/">About</a><a href="/" class="current">Contact</a></nav>
    </div>
</header>
<main class="wrap">
    <?= $content ?>
</main>
<footer class="wrap site-footer">
    © <?= date('Y') ?> <?= $this->e($companyName) ?> · Your data is used only to answer your message.
</footer>
</body>
</html>
