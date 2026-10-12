<?php
/** @var \ContactForm\Support\View $this */
/** @var list<array<string, mixed>> $messages */
/** @var array{total: int, unread: int} $counts */
/** @var bool $unreadOnly */
/** @var array<string, mixed>|null $selected */
/** @var int $spamBlocked */
use ContactForm\Domain\Topic;

$mailLabels = ['sent' => 'E-mail sent', 'failed' => 'E-mail failed', 'disabled' => 'Stored only (SMTP off)'];
?>
<div class="inbox">
    <aside class="inbox-list">
        <div class="inbox-tabs">
            <a href="/inbox"<?= !$unreadOnly ? ' class="active"' : '' ?>>All <span class="count"><?= $this->e($counts['total']) ?></span></a>
            <a href="/inbox?show=unread"<?= $unreadOnly ? ' class="active"' : '' ?>>Unread <span class="count"><?= $this->e($counts['unread']) ?></span></a>
        </div>
        <ul>
            <?php foreach ($messages as $m): ?>
                <li class="<?= $m['read_at'] === null ? 'unread' : '' ?><?= $selected !== null && $selected['id'] === $m['id'] ? ' selected' : '' ?>">
                    <a href="/inbox/<?= (int) $m['id'] ?>">
                        <span class="from"><?= $this->e($m['name']) ?></span>
                        <span class="when"><?= $this->e((new DateTimeImmutable($m['created_at']))->format('d/m H:i')) ?></span>
                        <span class="topic"><?= $this->e(Topic::from($m['topic'])->label()) ?><?= $m['company'] !== '' ? ' · ' . $this->e($m['company']) : '' ?></span>
                        <span class="snippet"><?= $this->e(mb_strimwidth($m['body'], 0, 90, '...')) ?></span>
                    </a>
                </li>
            <?php endforeach ?>
            <?php if ($messages === []): ?><li class="empty">No messages here.</li><?php endif ?>
        </ul>
        <p class="spam-note"><?= $this->e($spamBlocked) ?> spam submission(s) blocked by the honeypot and timing checks.</p>
    </aside>

    <section class="inbox-read">
        <?php if ($selected === null): ?>
            <div class="placeholder">Select a message to read it.</div>
        <?php else: ?>
            <div class="read-head">
                <div>
                    <h1><?= $this->e(Topic::from($selected['topic'])->label()) ?></h1>
                    <p class="muted"><?= $this->e((new DateTimeImmutable($selected['created_at']))->format('d/m/Y \a\t H:i')) ?> · <?= $this->e($mailLabels[$selected['mail_status']] ?? $selected['mail_status']) ?></p>
                </div>
                <div class="read-actions">
                    <a class="btn btn-small btn-primary" href="mailto:<?= $this->e($selected['email']) ?>?subject=<?= $this->e(rawurlencode('Re: ' . Topic::from($selected['topic'])->label())) ?>">Reply</a>
                    <form method="post" action="/inbox/<?= (int) $selected['id'] ?>/unread"><?= $this->csrf() ?><button class="btn btn-small" type="submit">Mark unread</button></form>
                    <form method="post" action="/inbox/<?= (int) $selected['id'] ?>/delete"><?= $this->csrf() ?><button class="btn btn-small btn-danger" type="submit">Delete</button></form>
                </div>
            </div>
            <dl class="sender">
                <dt>From</dt><dd><?= $this->e($selected['name']) ?></dd>
                <dt>E-mail</dt><dd><?= $this->e($selected['email']) ?></dd>
                <?php if ($selected['phone'] !== ''): ?><dt>Phone</dt><dd><?= $this->e($selected['phone']) ?></dd><?php endif ?>
                <?php if ($selected['company'] !== ''): ?><dt>Company</dt><dd><?= $this->e($selected['company']) ?></dd><?php endif ?>
            </dl>
            <div class="body"><?= nl2br($this->e($selected['body'])) ?></div>
        <?php endif ?>
    </section>
</div>
