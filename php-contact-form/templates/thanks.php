<?php
/** @var \ContactForm\Support\View $this */
/** @var string|null $name */
?>
<section class="card narrow center">
    <h1>Message sent</h1>
    <p class="lead"><?= $name !== null ? 'Thank you, ' . $this->e($name) . '. ' : 'Thank you. ' ?>We received your message and will reply within one business day.</p>
    <a class="btn" href="/">Back to the form</a>
</section>
