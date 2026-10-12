<?php
/** @var \MiniCrm\Support\View $this */
/** @var int $status */
/** @var string $message */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $this->e($status) ?> · Mini CRM</title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body class="auth">
<div class="card auth-card">
    <h1>Error <?= $this->e($status) ?></h1>
    <p><?= $this->e($message) ?></p>
    <a class="btn" href="/">Back to the dashboard</a>
</div>
</body>
</html>
