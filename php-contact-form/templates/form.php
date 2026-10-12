<?php
/** @var \ContactForm\Support\View $this */
/** @var array<string, mixed> $values */
/** @var array<string, string> $errors */
/** @var string|null $alert */
/** @var list<\ContactForm\Domain\Topic> $topics */
use ContactForm\App;

$v = static fn (string $key): string => is_string($values[$key] ?? null) ? $values[$key] : '';
$err = fn (string $key): string => isset($errors[$key]) ? '<span class="error" id="' . $key . '-error">' . $this->e($errors[$key]) . '</span>' : '';
$cls = static fn (string $key): string => isset($errors[$key]) ? ' class="field has-error"' : ' class="field"';
$aria = static fn (string $key): string => isset($errors[$key]) ? ' aria-invalid="true" aria-describedby="' . $key . '-error"' : '';
?>
<div class="contact">
    <section class="contact-intro">
        <p class="eyebrow">Contact</p>
        <h1>Tell us about your project</h1>
        <p class="lead">Maintenance contracts, new installations or a quick question: send a message and our team
            replies within one business day.</p>
        <dl class="contact-facts">
            <dt>Opening hours</dt><dd>Monday to Friday, 8 am to 6 pm</dd>
            <dt>Response time</dt><dd>Up to 1 business day</dd>
            <dt>Service area</dt><dd>Porto Alegre and metropolitan region</dd>
        </dl>
    </section>

    <form method="post" action="/" class="card contact-form" novalidate>
        <?php if ($alert !== null): ?>
            <div class="alert alert-error" role="alert"><?= $this->e($alert) ?></div>
        <?php elseif ($errors !== []): ?>
            <div class="alert alert-error" role="alert">Please check the highlighted fields.</div>
        <?php endif ?>

        <?= $this->csrf() ?>
        <div class="row">
            <label<?= $cls('name') ?>>Name
                <input type="text" name="name" value="<?= $this->e($v('name')) ?>" required maxlength="100" autocomplete="name"<?= $aria('name') ?>>
                <?= $err('name') ?>
            </label>
            <label<?= $cls('company') ?>><span>Company <span class="optional">(optional)</span></span>
                <input type="text" name="company" value="<?= $this->e($v('company')) ?>" maxlength="120" autocomplete="organization"<?= $aria('company') ?>>
                <?= $err('company') ?>
            </label>
        </div>
        <div class="row">
            <label<?= $cls('email') ?>>E-mail
                <input type="email" name="email" value="<?= $this->e($v('email')) ?>" required maxlength="254" autocomplete="email"<?= $aria('email') ?>>
                <?= $err('email') ?>
            </label>
            <label<?= $cls('phone') ?>><span>Phone / WhatsApp <span class="optional">(optional)</span></span>
                <input type="tel" name="phone" value="<?= $this->e($v('phone')) ?>" maxlength="20" autocomplete="tel"<?= $aria('phone') ?>>
                <?= $err('phone') ?>
            </label>
        </div>
        <label<?= $cls('topic') ?>>Subject
            <select name="topic" required<?= $aria('topic') ?>>
                <option value="">Choose...</option>
                <?php foreach ($topics as $t): ?>
                    <option value="<?= $this->e($t->value) ?>"<?= $v('topic') === $t->value ? ' selected' : '' ?>><?= $this->e($t->label()) ?></option>
                <?php endforeach ?>
            </select>
            <?= $err('topic') ?>
        </label>
        <label<?= $cls('message') ?>>Message
            <textarea name="message" rows="6" required minlength="10" maxlength="5000"<?= $aria('message') ?>><?= $this->e($v('message')) ?></textarea>
            <?= $err('message') ?>
        </label>

        <!-- Honeypot: invisible to people, irresistible to bots. Must stay empty. -->
        <div class="hp" aria-hidden="true">
            <label>Website <input type="text" name="<?= App::HONEYPOT ?>" tabindex="-1" autocomplete="off"></label>
        </div>

        <label class="check<?= isset($errors['consent']) ? ' has-error' : '' ?>">
            <input type="checkbox" name="consent" value="yes"<?= ($values['consent'] ?? '') === 'yes' ? ' checked' : '' ?>>
            <span>I agree that my data is stored to answer this message (LGPD).</span>
        </label>
        <?= $err('consent') ?>

        <button type="submit" class="btn btn-primary">Send message</button>
    </form>
</div>
