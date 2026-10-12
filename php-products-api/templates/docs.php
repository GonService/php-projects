<?php
/** @var \Closure(mixed): string $e */
/** @var string $sampleJson */
$endpoints = [
    ['GET', '/api/products', false, 'List products. Filters: q, category_id, min_price, max_price, active, in_stock. Sort: name, sku, price_cents, stock, created_at (prefix "-" for descending). Pagination: page, per_page (max 100).'],
    ['GET', '/api/products/{id}', false, 'One product.'],
    ['POST', '/api/products', true, 'Create. Required: sku, name, price_cents. Optional: description, stock, active, category_id.'],
    ['PUT', '/api/products/{id}', true, 'Replace all fields (missing optional fields get their defaults).'],
    ['PATCH', '/api/products/{id}', true, 'Change only the fields sent.'],
    ['DELETE', '/api/products/{id}', true, 'Delete. Returns 204.'],
    ['GET', '/api/categories', false, 'List categories with product_count. Filter: q. Sort: name, slug, created_at.'],
    ['GET', '/api/categories/{id}', false, 'One category.'],
    ['POST', '/api/categories', true, 'Create. Required: name. Optional: slug (generated from the name), description.'],
    ['PUT', '/api/categories/{id}', true, 'Replace all fields.'],
    ['PATCH', '/api/categories/{id}', true, 'Change only the fields sent.'],
    ['DELETE', '/api/categories/{id}', true, 'Delete. 409 while the category still has products.'],
    ['GET', '/api/health', false, 'Health check.'],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Products API</title>
    <link rel="stylesheet" href="/assets/docs.css">
</head>
<body>
<header>
    <div class="wrap">
        <h1>Products API <span class="version">v1</span></h1>
        <p>REST JSON API for a product catalog. Reads are public; writes need an API key.
            Full contract: <a href="/openapi">docs/openapi.yaml</a> (OpenAPI 3.0).</p>
    </div>
</header>
<main class="wrap">
    <section>
        <h2>Endpoints</h2>
        <table>
            <thead><tr><th>Method</th><th>Path</th><th>Auth</th><th>Description</th></tr></thead>
            <tbody>
            <?php foreach ($endpoints as [$method, $path, $auth, $text]): ?>
                <tr>
                    <td><span class="method m-<?= $e(strtolower($method)) ?>"><?= $e($method) ?></span></td>
                    <td><code><?= $e($path) ?></code></td>
                    <td><?= $auth ? '<span class="key">API key</span>' : '<span class="muted">public</span>' ?></td>
                    <td><?= $e($text) ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </section>

    <div class="cols">
        <section>
            <h2>Conventions</h2>
            <dl>
                <dt>Auth</dt><dd><code>X-API-Key: pk_...</code> or <code>Authorization: Bearer pk_...</code> on POST, PUT, PATCH and DELETE.</dd>
                <dt>Caching</dt><dd>Every GET returns an <code>ETag</code>; send it back in <code>If-None-Match</code> to get <code>304 Not Modified</code>.</dd>
                <dt>Concurrency</dt><dd>Send <code>If-Match</code> on product writes to get <code>412</code> instead of overwriting someone else's change.</dd>
                <dt>Money</dt><dd>Prices are integers in cents (<code>price_cents</code>), currency BRL.</dd>
                <dt>Errors</dt><dd>Always <code>{"error": {"status", "code", "message", "details"}}</code>; validation is <code>422</code> with messages per field.</dd>
            </dl>
        </section>
        <section>
            <h2>Live example</h2>
            <p class="muted"><code>GET /api/products?per_page=1&amp;sort=-price_cents</code></p>
            <pre><?= $e($sampleJson) ?></pre>
        </section>
    </div>
</main>
</body>
</html>
