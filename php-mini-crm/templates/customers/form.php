<?php
/** @var \MiniCrm\Support\View $this */
/** @var int|null $id */
/** @var array<string, mixed> $values */
/** @var array<string, string> $errors */
/** @var list<\MiniCrm\Domain\CustomerStatus> $statuses */
use MiniCrm\Domain\Document;

$v = static fn (string $key): string => (string) ($values[$key] ?? '');
$field = function (string $name, string $label, string $type = 'text', string $extra = '') use ($v, $errors): string {
    $error = $errors[$name] ?? null;

    return '<label' . ($error !== null ? ' class="has-error"' : '') . '>' . $this->e($label)
        . ' <input type="' . $type . '" name="' . $name . '" value="' . $this->e($name === 'document' ? Document::format($v($name)) : $v($name)) . '"' . $extra . '>'
        . ($error !== null ? '<span class="error">' . $this->e($error) . '</span>' : '')
        . '</label>';
};
?>
<div class="page-head">
    <h1><?= $id === null ? 'New customer' : 'Edit customer' ?></h1>
    <a class="btn" href="<?= $id === null ? '/customers' : '/customers/' . $id ?>">Cancel</a>
</div>

<form method="post" action="<?= $id === null ? '/customers' : '/customers/' . $id ?>" class="card form-grid" novalidate>
    <?= $this->csrf() ?>
    <?= $field('name', 'Company or person name', 'text', ' required maxlength="120"') ?>
    <?= $field('document', 'CPF or CNPJ', 'text', ' required placeholder="00.000.000/0000-00"') ?>
    <?= $field('contact_name', 'Contact person', 'text', ' maxlength="120"') ?>
    <?= $field('email', 'E-mail', 'email') ?>
    <?= $field('phone', 'Phone', 'tel', ' placeholder="(51) 99999-0000"') ?>
    <?= $field('city', 'City', 'text', ' maxlength="80"') ?>
    <label<?= isset($errors['status']) ? ' class="has-error"' : '' ?>>Status
        <select name="status">
            <?php foreach ($statuses as $s): ?>
                <option value="<?= $this->e($s->value) ?>"<?= $v('status') === $s->value ? ' selected' : '' ?>><?= $this->e($s->label()) ?></option>
            <?php endforeach ?>
        </select>
        <?php if (isset($errors['status'])): ?><span class="error"><?= $this->e($errors['status']) ?></span><?php endif ?>
    </label>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $id === null ? 'Create customer' : 'Save changes' ?></button>
    </div>
</form>
