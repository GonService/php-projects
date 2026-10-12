<?php
/** @var \ContactForm\Support\View $this */
/** @var int $status */
/** @var string $message */
?>
<section class="card narrow center">
    <h1>Error <?= $this->e($status) ?></h1>
    <p><?= $this->e($message) ?></p>
    <a class="btn" href="/">Back to the form</a>
</section>
