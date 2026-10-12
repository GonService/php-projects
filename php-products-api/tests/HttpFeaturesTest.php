<?php

declare(strict_types=1);

use ProductsApi\Domain\ApiKeys;

test('Auth: write routes need a valid API key; reads are public', function (): void {
    $api = new Api();
    $res = $api->request('POST', '/api/products', [], ['sku' => 'X1', 'name' => 'X', 'price_cents' => 1], auth: false);
    assertError($res, 401, 'unauthorized');
    assertSame('Bearer realm="products-api"', $res->headers['WWW-Authenticate']);
    assertError($api->request('DELETE', '/api/products/1', [], null, ['x-api-key' => 'pk_wrong']), 401, 'unauthorized');
    assertSame(200, $api->get('/api/products/1')->status);
});

test('Auth: Bearer header works and revoked keys stop working', function (): void {
    $api = new Api();
    $keys = new ApiKeys($api->pdo);
    $extra = $keys->create('erp', '2026-10-01 12:00:00');
    $patch = static fn () => $api->request('PATCH', '/api/products/1', [], ['stock' => 3], ['authorization' => 'Bearer ' . $extra['key']], auth: false);
    assertSame(200, $patch()->status);
    $keys->revoke($extra['id'], '2026-10-12 00:00:00');
    assertError($patch(), 401, 'unauthorized');
});

test('Auth: keys are stored only as hashes', function (): void {
    $api = new Api();
    $stored = (string) $api->pdo->query('SELECT key_hash FROM api_keys')->fetchColumn();
    assertSame(hash('sha256', $api->key), $stored);
    assertTrue(str_starts_with($api->key, 'pk_') && strlen($api->key) === 67, 'key format');
});

test('ETag: GET returns an ETag and If-None-Match gives 304', function (): void {
    $api = new Api();
    $first = $api->get('/api/products/1');
    $etag = $first->headers['ETag'];
    assertTrue(preg_match('/^"[0-9a-f]{64}"$/', $etag) === 1, 'strong etag');

    $cached = $api->get('/api/products/1', [], ['if-none-match' => $etag]);
    assertSame(304, $cached->status);
    assertSame('', $cached->body);
    assertSame(304, $api->get('/api/products/1', [], ['if-none-match' => '"other", W/' . $etag])->status);

    $api->request('PATCH', '/api/products/1', [], ['stock' => 99]);
    $changed = $api->get('/api/products/1', [], ['if-none-match' => $etag]);
    assertSame(200, $changed->status);
    assertTrue($changed->headers['ETag'] !== $etag, 'new etag');
});

test('ETag: lists have ETags too', function (): void {
    $api = new Api();
    $etag = $api->get('/api/products', ['sort' => '-price_cents'])->headers['ETag'];
    assertSame(304, $api->get('/api/products', ['sort' => '-price_cents'], ['if-none-match' => $etag])->status);
    assertSame(200, $api->get('/api/products', ['sort' => 'price_cents'], ['if-none-match' => $etag])->status);
});

test('If-Match: stale writes get 412, current ones pass', function (): void {
    $api = new Api();
    $etag = $api->get('/api/products/1')->headers['ETag'];
    $api->request('PATCH', '/api/products/1', [], ['stock' => 1]);
    assertError($api->request('PATCH', '/api/products/1', [], ['stock' => 2], ['if-match' => $etag]), 412, 'precondition_failed');

    $fresh = $api->get('/api/products/1')->headers['ETag'];
    $ok = $api->request('PATCH', '/api/products/1', [], ['stock' => 2], ['if-match' => $fresh]);
    assertSame(200, $ok->status);
    assertError($api->request('DELETE', '/api/products/1', [], null, ['if-match' => $fresh]), 412, 'precondition_failed');
});

test('Categories: CRUD with generated slug and 409 when in use', function (): void {
    $api = new Api();
    $res = $api->request('POST', '/api/categories', [], ['name' => 'Segurança & EPI']);
    assertSame(201, $res->status);
    $cat = body($res)['data'];
    assertSame('seguranca-epi', $cat['slug']);
    assertSame(0, $cat['product_count']);

    assertError($api->request('POST', '/api/categories', [], ['name' => 'Outra', 'slug' => 'seguranca-epi']), 422, 'validation_failed');
    assertSame('Equipamentos', body($api->request('PATCH', "/api/categories/{$cat['id']}", [], ['name' => 'Equipamentos']))['data']['name']);

    assertSame(3, body($api->get('/api/categories/1'))['data']['product_count']);
    assertError($api->request('DELETE', '/api/categories/1'), 409, 'category_in_use');
    assertSame(204, $api->request('DELETE', "/api/categories/{$cat['id']}")->status);
    assertSame(4, body($api->get('/api/categories'))['meta']['total']);
});

test('Errors: unexpected exceptions become a generic 500 JSON error', function (): void {
    $api = new Api();
    $api->pdo->exec('DROP TABLE products');
    $previous = ini_set('error_log', '/dev/null');
    $error = assertError($api->get('/api/products'), 500, 'server_error');
    ini_set('error_log', (string) $previous);
    assertNotContains('products', $error['message'], 'no internals leaked');
});

test('Docs: HTML page, OpenAPI file and health check', function (): void {
    $api = new Api();
    $html = $api->get('/');
    assertSame(200, $html->status);
    assertContains('/api/products/{id}', $html->body);
    assertContains('&quot;sku&quot;: &quot;SER-CIR-1400&quot;', $html->body);
    $yaml = $api->get('/openapi');
    assertContains('openapi: 3.0.3', $yaml->body);
    assertSame(['status' => 'ok'], body($api->get('/api/health')));
});

test('Docs: openapi.yaml documents exactly the routes the router serves', function (): void {
    $documented = [];
    $path = null;
    foreach (file(dirname(__DIR__) . '/docs/openapi.yaml', FILE_IGNORE_NEW_LINES) as $line) {
        if (preg_match('#^  (/api/\S+):$#', $line, $m) === 1) {
            $path = $m[1];
        } elseif ($path !== null && preg_match('/^    (get|post|put|patch|delete):$/', $line, $m) === 1) {
            $documented[] = strtoupper($m[1]) . ' ' . $path;
        } elseif (preg_match('/^\S/', $line) === 1) {
            $path = null;
        }
    }
    $served = (new Api(seed: false))->app->routeList();
    sort($documented);
    sort($served);
    assertSame($served, $documented);
});
