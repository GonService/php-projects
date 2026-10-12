<?php
/** @var \Modernized\Support\View $this */
/** @var int $status */
/** @var string $message */
?>
<section class="card">
    <h1>Error <?= $this->e($status) ?></h1>
    <p><?= $this->e($message) ?></p>
    <a href="/">Back to customers</a>
</section>
