<?php

declare(strict_types=1);

namespace ProductsApi\Domain;

use ProductsApi\Http\ApiException;

final class ProductValidator
{
    public const FIELDS = ['sku', 'name', 'description', 'price_cents', 'stock', 'active', 'category_id'];
    private const REQUIRED = ['sku', 'name', 'price_cents'];
    private const DEFAULTS = ['description' => '', 'stock' => 0, 'active' => true, 'category_id' => null];

    public function __construct(
        private readonly ProductRepository $products,
        private readonly CategoryRepository $categories,
    ) {
    }

    /**
     * POST and PUT send the whole product (missing optional fields get defaults);
     * PATCH sends only the fields to change, merged over $current.
     *
     * @param array<string, mixed>      $input
     * @param array<string, mixed>|null $current stored row when updating
     * @return array<string, mixed> data ready for the repository
     * @throws ApiException 422 with every field error
     */
    public function validate(array $input, ?array $current = null, bool $partial = false): array
    {
        $v = new Validation();
        $v->rejectUnknown($input, self::FIELDS);
        if ($partial && $input === []) {
            throw ApiException::validation(['body' => ['Send at least one field to change.']]);
        }

        if (!$partial) {
            foreach (self::REQUIRED as $field) {
                if (!array_key_exists($field, $input)) {
                    $v->add($field, 'This field is required.');
                }
            }
            $input += self::DEFAULTS;
        }

        $data = [];
        foreach (self::FIELDS as $field) {
            if (!array_key_exists($field, $input) || $v->has($field)) {
                continue;
            }
            $value = $input[$field];
            $data[$field] = match ($field) {
                'sku' => $this->sku($v, $value, $current),
                'name' => $v->string($value, 'name', 2, 120),
                'description' => $v->string($value, 'description', 0, 2000),
                'price_cents' => $v->int($value, 'price_cents', 0, 100_000_000),
                'stock' => $v->int($value, 'stock', 0, 1_000_000),
                'active' => $v->bool($value, 'active'),
                'category_id' => $this->category($v, $value),
            };
        }

        if ($v->errors() !== []) {
            throw ApiException::validation($v->errors());
        }

        return $data;
    }

    private function sku(Validation $v, mixed $value, ?array $current): ?string
    {
        if (!is_string($value) || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{1,39}$/', $value) !== 1) {
            $v->add('sku', 'Use 2 to 40 letters, digits, dots, dashes or underscores.');

            return null;
        }
        $sku = strtoupper($value);
        if ($this->products->skuTaken($sku, $current !== null ? (int) $current['id'] : null)) {
            $v->add('sku', 'Another product already uses this SKU.');
        }

        return $sku;
    }

    private function category(Validation $v, mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }
        if (!is_int($value)) {
            $v->add('category_id', 'Must be an integer or null.');

            return null;
        }
        if ($this->categories->find($value) === null) {
            $v->add('category_id', 'Category does not exist.');
        }

        return $value;
    }
}
