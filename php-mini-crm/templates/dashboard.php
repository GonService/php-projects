<?php
/** @var \MiniCrm\Support\View $this */
/** @var string $today */
/** @var array<string, int> $statusCounts */
/** @var array{open: int, overdue: int, today: int} $reminderCounts */
/** @var list<array<string, mixed>> $followUps */
/** @var list<array<string, mixed>> $recent */
?>
<div class="page-head">
    <h1>Dashboard</h1>
    <span class="muted"><?= $this->date($today, 'l, d/m/Y') ?></span>
</div>

<section class="stats">
    <a class="stat" href="/customers?status=active"><span class="stat-value"><?= $this->e($statusCounts['active']) ?></span> Active customers</a>
    <a class="stat" href="/customers?status=lead"><span class="stat-value"><?= $this->e($statusCounts['lead']) ?></span> Leads</a>
    <a class="stat" href="/customers?follow_up=open"><span class="stat-value"><?= $this->e($reminderCounts['open']) ?></span> Open follow-ups</a>
    <a class="stat<?= $reminderCounts['overdue'] > 0 ? ' stat-alert' : '' ?>" href="/customers?follow_up=overdue"><span class="stat-value"><?= $this->e($reminderCounts['overdue']) ?></span> Overdue</a>
</section>

<div class="grid-2">
    <section class="card">
        <h2>Next follow-ups</h2>
        <?php if ($followUps === []): ?>
            <p class="muted">Nothing scheduled. Add a follow-up from a customer page.</p>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($followUps as $r): ?>
                    <li class="list-row<?= $r['overdue'] ? ' overdue' : '' ?>">
                        <div>
                            <strong><?= $this->e($r['title']) ?></strong>
                            <a class="muted small" href="/customers/<?= (int) $r['customer_id'] ?>"><?= $this->e($r['customer_name']) ?></a>
                        </div>
                        <div class="row-actions">
                            <span class="badge<?= $r['overdue'] ? ' badge-danger' : ($r['due_date'] === $today ? ' badge-warn' : '') ?>">
                                <?= $r['overdue'] ? 'Overdue · ' : ($r['due_date'] === $today ? 'Today · ' : '') ?><?= $this->date($r['due_date']) ?>
                            </span>
                            <form method="post" action="/reminders/<?= (int) $r['id'] ?>/done">
                                <?= $this->csrf() ?>
                                <input type="hidden" name="back" value="dashboard">
                                <button class="btn btn-small" type="submit">Done</button>
                            </form>
                        </div>
                    </li>
                <?php endforeach ?>
            </ul>
        <?php endif ?>
    </section>

    <section class="card">
        <h2>Recent activity</h2>
        <?php if ($recent === []): ?>
            <p class="muted">No interactions yet.</p>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($recent as $i): ?>
                    <li class="list-row list-row-stack">
                        <div class="small muted">
                            <?= $this->e(\MiniCrm\Domain\InteractionType::from($i['type'])->label()) ?> ·
                            <a href="/customers/<?= (int) $i['customer_id'] ?>"><?= $this->e($i['customer_name']) ?></a> ·
                            <?= $this->date($i['created_at'], 'd/m H:i') ?>
                        </div>
                        <div class="clamp"><?= $this->e($i['body']) ?></div>
                    </li>
                <?php endforeach ?>
            </ul>
        <?php endif ?>
    </section>
</div>
