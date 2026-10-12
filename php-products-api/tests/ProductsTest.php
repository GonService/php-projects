<?php

declare(strict_types=1);

test('Products: list is paginated with meta and links', function (): void {
    $res = (new Api())->get('/api/products', ['per_page' => '4', 'page' => '2']);
    assertSame(200, $res->status);
    $doc = body($res);
    assertSame(4, count($doc['data']));
    assertSame(['page' => 2, 'per_page' => 4, 'total' => 10, 'total_pages' => 3], $doc['meta']);
    assertSame('/api/products?per_page=4&page=3', $doc['links']['next']);
    assertSame('/api/products?per_page=4&page=1', $doc['links']['prev']);
    assertSame('application/json; charset=utf-8', $res->headers['Content-Type']);
});

test('Products: filter by text, category, price range, active and stock', function (): void {
    $api = new Api();
    $skus = static fn ($res): array => array_column(body($res)['data'], 'sku');
    assertSame(['LED-9W-6500', 'REF-LED-50W'], $skus($api->get('/api/products', ['q' => 'led', 'sort' => 'price_cents'])));
    assertSame(['TUB-PVC-25', 'REG-GAV-34'], $skus($api->get('/api/products', ['category_id' => '4', 'sort' => 'price_cents'])));
    assertSame(['DISJ-20A', 'TOM-10A-BR'], $skus($api->get('/api/products', ['min_price' => '1000', 'max_price' => '2000', 'sort' => '-price_cents'])));
    assertSame(['LIX-ORB-220'], $skus($api->get('/api/products', ['active' => 'false'])));
    assertSame(['LIX-ORB-220'], $skus($api->get('/api/products', ['in_stock' => 'false'])));
});

test('Products: sorting by several columns', function (): void {
    $data = body((new Api())->get('/api/products', ['sort' => '-stock,name', 'per_page' => '3']))['data'];
    assertSame([500, 310, 220], array_column($data, 'stock'));
});

test('Products: invalid query parameters give 422 invalid_query', function (): void {
    $api = new Api();
    $error = assertError($api->get('/api/products', ['per_page' => '500', 'sort' => 'password', 'active' => 'maybe']), 422, 'invalid_query');
    assertSame(['per_page', 'active', 'sort'], array_keys($error['details']));
    assertError($api->get('/api/products', ['page' => '99']), 422, 'invalid_query');
    assertError($api->get('/api/products', ['sort' => 'name;DROP TABLE products']), 422, 'invalid_query');
});

test('Products: search input is not SQL', function (): void {
    $api = new Api();
    assertSame(0, body($api->get('/api/products', ['q' => "' OR 1=1 --"]))['meta']['total']);
    assertSame(0, body($api->get('/api/products', ['q' => '%']))['meta']['total']);
    assertSame(10, body($api->get('/api/products'))['meta']['total']);
});

test('Products: show one with nested category and typed fields', function (): void {
    $data = body((new Api())->get('/api/products/1'))['data'];
    assertSame('FUR-650W', $data['sku']);
    assertSame(28990, $data['price_cents']);
    assertSame(true, $data['active']);
    assertSame(['id' => 1, 'name' => 'Ferramentas elétricas', 'slug' => 'ferramentas-eletricas'], $data['category']);
    assertSame('2026-10-01T12:00:00+00:00', $data['created_at']);
});

test('Products: unknown id is 404 and bad paths are 404 / 405', function (): void {
    $api = new Api();
    assertError($api->get('/api/products/999'), 404, 'not_found');
    assertError($api->get('/api/products/abc'), 404, 'not_found');
    $res = $api->request('POST', '/api/products/1', [], ['name' => 'x']);
    assertError($res, 405, 'method_not_allowed');
    assertSame('GET, PUT, PATCH, DELETE', $res->headers['Allow']);
});

test('Products: create returns 201 with Location', function (): void {
    $api = new Api();
    $res = $api->request('POST', '/api/products', [], [
        'sku' => 'fit-iso-19', 'name' => 'Fita isolante 19 mm x 20 m', 'price_cents' => 790, 'stock' => 120, 'category_id' => 2,
    ]);
    assertSame(201, $res->status);
    assertSame('/api/products/11', $res->headers['Location']);
    $data = body($res)['data'];
    assertSame('FIT-ISO-19', $data['sku']);
    assertSame('', $data['description']);
    assertSame(true, $data['active']);
    assertSame('Material elétrico', $data['category']['name']);
    assertSame('2026-10-12T15:00:00+00:00', $data['updated_at']);
});

test('Products: validation errors use the 422 shape with every field', function (): void {
    $error = assertError((new Api())->request('POST', '/api/products', [], [
        'sku' => 'DISJ-20A', 'price_cents' => 19.9, 'stock' => -1, 'category_id' => 99, 'colour' => 'blue', 'id' => 5,
    ]), 422, 'validation_failed');
    assertSame('The given data was invalid.', $error['message']);
    assertSame(['Unknown field.'], $error['details']['colour']);
    assertSame(['This field is read-only.'], $error['details']['id']);
    assertSame(['This field is required.'], $error['details']['name']);
    assertSame(['Another product already uses this SKU.'], $error['details']['sku']);
    assertSame(['Must be an integer.'], $error['details']['price_cents']);
    assertSame(['Must be between 0 and 1000000.'], $error['details']['stock']);
    assertSame(['Category does not exist.'], $error['details']['category_id']);
});

test('Products: PATCH changes only the given fields, PUT replaces', function (): void {
    $api = new Api();
    $patched = body($api->request('PATCH', '/api/products/2', [], ['price_cents' => 49900, 'active' => false]))['data'];
    assertSame(49900, $patched['price_cents']);
    assertSame(false, $patched['active']);
    assertSame('Serra circular 1400 W 7 1/4"', $patched['name']);

    $put = body($api->request('PUT', '/api/products/2', [], ['sku' => 'SER-CIR-1400', 'name' => 'Serra circular', 'price_cents' => 50000]))['data'];
    assertSame(0, $put['stock'], 'defaults restored');
    assertSame(true, $put['active']);
    assertSame(null, $put['category']);
    assertError($api->request('PUT', '/api/products/2', [], ['name' => 'Only name']), 422, 'validation_failed');
});

test('Products: DELETE returns 204 and the product is gone', function (): void {
    $api = new Api();
    $res = $api->request('DELETE', '/api/products/3');
    assertSame(204, $res->status);
    assertSame('', $res->body);
    assertSame(404, $api->get('/api/products/3')->status);
});

test('Body: wrong content type is 415, broken JSON is 400', function (): void {
    $api = new Api();
    assertError($api->request('POST', '/api/products', [], 'sku=X', ['content-type' => 'application/x-www-form-urlencoded']), 415, 'unsupported_media_type');
    assertError($api->request('POST', '/api/products', [], '{"sku": '), 400, 'invalid_json');
    assertError($api->request('POST', '/api/products', [], '[1, 2]'), 400, 'invalid_json');
});

test('Products: PATCH with an empty object is a 422', function (): void {
    $error = assertError((new Api())->request('PATCH', '/api/products/1', [], new stdClass()), 422, 'validation_failed');
    assertSame(['Send at least one field to change.'], $error['details']['body']);
});
