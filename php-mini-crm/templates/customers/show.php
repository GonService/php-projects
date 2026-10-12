<?php
/** @var \MiniCrm\Support\View $this */
/** @var array<string, mixed> $customer */
/** @var string $today */
/** @var list<array<string, mixed>> $interactions */
/** @var list<array<string, mixed>> $reminders */
/** @var list<\MiniCrm\Domain\InteractionType> $types */
use MiniCrm\Domain\CustomerStatus;
use MiniCrm\Domain\Document;
use MiniCrm\Domain\InteractionType;

$document = Document::tryParse($customer['document']);
?>
<div class="page-head">
    <div>
        <a class="small muted" href="/customers">Customers</a>
        <h1><?= $this->e($customer['name']) ?>
            <span class="pill pill-<?= $this->e($customer['status']) ?>"><?= $this->e(CustomerStatus::from($customer['status'])->label()) ?></span>
        </h1>
    </div>
    <a class="btn" href="/customers/<?= (int) $customer['id'] ?>/edit">Edit</a>
</div>

<div class="grid-sidebar">
    <aside>
        <section class="card">
            <h2>Details</h2>
            <dl class="details">
                <dt><?= $this->e($document?->type->label() ?? 'Document') ?></dt><dd class="mono"><?= $this->e($document?->formatted() ?? $customer['document']) ?></dd>
                <dt>Contact</dt><dd><?= $this->e($customer['contact_name'] ?: '-') ?></dd>
                <dt>E-mail</dt><dd><?php if ($customer['email'] !== ''): ?><a href="mailto:<?= $this->e($customer['email']) ?>"><?= $this->e($customer['email']) ?></a><?php else: ?>-<?php endif ?></dd>
                <dt>Phone</dt><dd><?= $this->e($customer['phone'] ?: '-') ?></dd>
                <dt>City</dt><dd><?= $this->e($customer['city'] ?: '-') ?></dd>
                <dt>Since</dt><dd><?= $this->date($customer['created_at']) ?></dd>
            </dl>
        </section>

        <section class="card">
            <h2>Follow-ups</h2>
            <ul class="list">
                <?php foreach ($reminders as $r): ?>
                    <li class="list-row<?= $r['overdue'] ? ' overdue' : '' ?><?= $r['done_at'] !== null ? ' done' : '' ?>">
                        <div>
                            <strong><?= $this->e($r['title']) ?></strong>
                            <div class="small muted">
                                <?= $r['done_at'] !== null ? 'Done ' . $this->date($r['done_at']) : ($r['overdue'] ? 'Overdue since ' : 'Due ') . $this->date($r['due_date']) ?>
                            </div>
                        </div>
                        <?php if ($r['done_at'] === null): ?>
                            <form method="post" action="/reminders/<?= (int) $r['id'] ?>/done">
                                <?= $this->csrf() ?>
                                <button class="btn btn-small" type="submit">Done</button>
                            </form>
                        <?php endif ?>
                    </li>
                <?php endforeach ?>
                <?php if ($reminders === []): ?><li class="muted small">No follow-ups yet.</li><?php endif ?>
            </ul>
            <form method="post" action="/customers/<?= (int) $customer['id'] ?>/reminders" class="inline-form">
                <?= $this->csrf() ?>
                <input type="text" name="title" placeholder="e.g. Send proposal" required maxlength="200" aria-label="Follow-up">
                <input type="date" name="due_date" value="<?= $this->e($today) ?>" required aria-label="Due date">
                <button type="submit" class="btn">Schedule</button>
            </form>
        </section>
    </aside>

    <section class="card">
        <h2>Timeline</h2>
        <form method="post" action="/customers/<?= (int) $customer['id'] ?>/interactions" class="note-form">
            <?= $this->csrf() ?>
            <textarea name="body" rows="3" placeholder="What happened? Call summary, e-mail, meeting notes..." required maxlength="5000" aria-label="Note"></textarea>
            <div class="note-actions">
                <select name="type" aria-label="Type">
                    <?php foreach ($types as $t): ?>
                        <option value="<?= $this->e($t->value) ?>"><?= $this->e($t->label()) ?></option>
                    <?php endforeach ?>
                </select>
                <button type="submit" class="btn btn-primary">Add to timeline</button>
            </div>
        </form>
        <ol class="timeline">
            <?php foreach ($interactions as $i): ?>
                <li class="timeline-item type-<?= $this->e($i['type']) ?>">
                    <div class="small muted">
                        <strong><?= $this->e(InteractionType::from($i['type'])->label()) ?></strong>
                        · <?= $this->date($i['created_at'], 'd/m/Y H:i') ?>
                        <?php if ($i['author'] !== null): ?>· <?= $this->e($i['author']) ?><?php endif ?>
                    </div>
                    <p><?= nl2br($this->e($i['body'])) ?></p>
                </li>
            <?php endforeach ?>
            <?php if ($interactions === []): ?><li class="muted small">No interactions recorded yet.</li><?php endif ?>
        </ol>
    </section>
</div>
