<?php
/** @var \ContactForm\Support\View $this */
/** @var string|null $error */
?>
<form method="post" action="/inbox/login" class="card narrow">
    <h1>Inbox</h1>
    <p class="muted">Enter the inbox password to read messages.</p>
    <?php if ($error !== null): ?><div class="alert alert-error" role="alert"><?= $this->e($error) ?></div><?php endif ?>
    <?= $this->csrf() ?>
    <label class="field">Password <input type="password" name="password" required autofocus autocomplete="current-password"></label>
    <button type="submit" class="btn btn-primary">Open inbox</button>
</form>
