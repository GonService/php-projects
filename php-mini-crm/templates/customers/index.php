<?php
/** @var \MiniCrm\Support\View $this */
/** @var \MiniCrm\Domain\CustomerFilter $filter */
/** @var list<array<string, mixed>> $customers */
use MiniCrm\Domain\CustomerStatus;
use MiniCrm\Domain\Document;
?>
<div class="page-head">
    <h1>Customers</h1>
    <a class="btn btn-primary" href="/customers/new">New customer</a>
</div>

<form method="get" action="/customers" class="filters card">
    <label class="grow">Search
        <input type="search" name="q" value="<?= $this->e($filter->query) ?>" placeholder="Name, contact, e-mail, city or CPF/CNPJ">
    </label>
    <label>Status
        <select name="status">
            <option value="">All</option>
            <?php foreach (CustomerStatus::cases() as $s): ?>
                <option value="<?= $this->e($s->value) ?>"<?= $filter->status === $s ? ' selected' : '' ?>><?= $this->e($s->label()) ?></option>
            <?php endforeach ?>
        </select>
    </label>
    <label>Follow-up
        <select name="follow_up">
            <option value="">Any</option>
            <option value="open"<?= $filter->followUp === 'open' ? ' selected' : '' ?>>Has open follow-up</option>
            <option value="overdue"<?= $filter->followUp === 'overdue' ? ' selected' : '' ?>>Overdue</option>
        </select>
    </label>
    <button type="submit" class="btn">Filter</button>
</form>

<div class="card table-card">
    <table>
        <thead>
        <tr><th>Customer</th><th>CPF / CNPJ</th><th>Contact</th><th>City</th><th>Status</th><th>Next follow-up</th></tr>
        </thead>
        <tbody>
        <?php foreach ($customers as $c): ?>
            <tr<?= $c['overdue'] ? ' class="overdue"' : '' ?>>
                <td><a href="/customers/<?= (int) $c['id'] ?>"><strong><?= $this->e($c['name']) ?></strong></a></td>
                <td class="mono"><?= $this->e(Document::format($c['document'])) ?></td>
                <td><?= $this->e($c['contact_name']) ?><div class="small muted"><?= $this->e($c['email']) ?></div></td>
                <td><?= $this->e($c['city']) ?></td>
                <td><span class="pill pill-<?= $this->e($c['status']) ?>"><?= $this->e(CustomerStatus::from($c['status'])->label()) ?></span></td>
                <td>
                    <?php if ($c['next_due'] === null): ?>
                        <span class="muted">None</span>
                    <?php else: ?>
                        <span class="badge<?= $c['overdue'] ? ' badge-danger' : '' ?>"><?= $c['overdue'] ? 'Overdue · ' : '' ?><?= $this->date($c['next_due']) ?></span>
                    <?php endif ?>
                </td>
            </tr>
        <?php endforeach ?>
        <?php if ($customers === []): ?>
            <tr><td colspan="6" class="empty">No customers match these filters.</td></tr>
        <?php endif ?>
        </tbody>
    </table>
</div>
<p class="small muted"><?= count($customers) ?> customer(s)</p>
