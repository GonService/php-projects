<?php
/** @var \MiniCrm\Support\View $this */
/** @var string $email */
/** @var string|null $error */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · Mini CRM</title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body class="auth">
<form method="post" action="/login" class="card auth-card">
    <div class="brand"><span class="brand-mark">M</span> Mini CRM</div>
    <h1>Sign in</h1>
    <?php if ($error !== null): ?>
        <div class="flash flash-error" role="alert"><?= $this->e($error) ?></div>
    <?php endif ?>
    <?= $this->csrf() ?>
    <label>E-mail <input type="email" name="email" value="<?= $this->e($email) ?>" required autofocus autocomplete="username"></label>
    <label>Password <input type="password" name="password" required autocomplete="current-password"></label>
    <button type="submit" class="btn btn-primary">Sign in</button>
</form>
</body>
</html>
