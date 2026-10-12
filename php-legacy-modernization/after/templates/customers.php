<?php
/** @var \Modernized\Support\View $this */
/** @var string $term */
/** @var list<\Modernized\Customers\Customer> $customers */
/** @var array<string, string> $old */
/** @var array<string, string> $errors */
/** @var string|null $flash */
$field = function (string $name, string $label, string $type) use ($old, $errors): string {
    $error = $errors[$name] ?? null;

    return '<label' . ($error !== null ? ' class="has-error"' : '') . '>' . $this->e($label)
        . '<input type="' . $type . '" name="' . $name . '" value="' . $this->e($old[$name] ?? '') . '">'
        . ($error !== null ? '<span class="error">' . $this->e($error) . '</span>' : '')
        . '</label>';
};
?>
<?php if ($flash !== null): ?>
    <div class="flash" role="status"><?= $this->e($flash) ?></div>
<?php endif ?>

<div class="layout">
    <section class="card">
        <form method="get" action="/" class="search">
            <input type="search" name="q" value="<?= $this->e($term) ?>" placeholder="Search by name" aria-label="Search by name">
            <button type="submit" class="btn">Search</button>
        </form>
        <?php if ($term !== ''): ?>
            <p class="muted">Results for <strong><?= $this->e($term) ?></strong> · <a href="/">clear</a></p>
        <?php endif ?>

        <table>
            <thead><tr><th>Name</th><th>E-mail</th><th>City</th><th>Since</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($customers as $c): ?>
                <tr>
                    <td><?= $this->e($c->name) ?></td>
                    <td><?= $this->e($c->email) ?></td>
                    <td><?= $this->e($c->city) ?></td>
                    <td><?= $this->e($c->createdAt->format('d/m/Y')) ?></td>
                    <td class="actions">
                        <form method="post" action="/customers/<?= $c->id ?>/delete">
                            <?= $this->csrf() ?>
                            <button type="submit" class="link-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach ?>
            <?php if ($customers === []): ?>
                <tr><td colspan="5" class="muted empty">No customers found.</td></tr>
            <?php endif ?>
            </tbody>
        </table>
    </section>

    <section class="card">
        <h2>New customer</h2>
        <form method="post" action="/customers" class="stack" novalidate>
            <?= $this->csrf() ?>
            <?= $field('name', 'Name', 'text') ?>
            <?= $field('email', 'E-mail', 'email') ?>
            <?= $field('city', 'City', 'text') ?>
            <button type="submit" class="btn btn-primary">Save customer</button>
        </form>
    </section>
</div>
