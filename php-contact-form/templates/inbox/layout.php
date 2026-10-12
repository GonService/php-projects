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
    <meta name="robots" content="noindex">
    <title>Inbox · <?= $this->e($companyName) ?></title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body class="inbox-body">
<header class="inbox-header">
    <div class="inbox-header-inner">
        <span class="logo"><?= $this->e($companyName) ?> <span class="muted">· Inbox</span></span>
        <form method="post" action="/inbox/logout"><?= $this->csrf() ?><button class="btn btn-small" type="submit">Sign out</button></form>
    </div>
</header>
<?= $content ?>
</body>
</html>
