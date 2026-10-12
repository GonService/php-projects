<?php

declare(strict_types=1);

namespace ProductsApi\Domain;

/** Turns database rows into the public JSON representation (types, nesting, ISO dates). */
final class Presenter
{
    /** @param array<string, mixed> $row */
    public static function product(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'sku' => $row['sku'],
            'name' => $row['name'],
            'description' => $row['description'],
            'price_cents' => (int) $row['price_cents'],
            'currency' => 'BRL',
            'stock' => (int) $row['stock'],
            'active' => (bool) $row['active'],
            'category' => $row['category_id'] === null ? null : [
                'id' => (int) $row['category_id'],
                'name' => $row['category_name'],
                'slug' => $row['category_slug'],
            ],
            'created_at' => self::date($row['created_at']),
            'updated_at' => self::date($row['updated_at']),
        ];
    }

    /** @param array<string, mixed> $row */
    public static function category(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'slug' => $row['slug'],
            'description' => $row['description'],
            'product_count' => (int) ($row['product_count'] ?? 0),
            'created_at' => self::date($row['created_at']),
            'updated_at' => self::date($row['updated_at']),
        ];
    }

    private static function date(string $value): string
    {
        return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))->format(\DateTimeInterface::ATOM);
    }
}
