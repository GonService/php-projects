<?php

declare(strict_types=1);

namespace ProductsApi\Domain;

use PDO;

final class ProductRepository
{
    public const SORTABLE = ['name', 'sku', 'price_cents', 'stock', 'created_at', 'updated_at', 'id'];
    public const FILTERS = [
        'q' => 'string', 'category_id' => 'int', 'min_price' => 'int', 'max_price' => 'int',
        'active' => 'bool', 'in_stock' => 'bool',
    ];

    private const SELECT = 'SELECT p.*, c.name AS category_name, c.slug AS category_slug
                            FROM products p LEFT JOIN categories c ON c.id = p.category_id';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array{items: list<array<string, mixed>>, total: int} */
    public function paginate(ListQuery $query): array
    {
        $where = [];
        $params = [];
        $f = $query->filters;
        if (isset($f['q'])) {
            $where[] = "(p.name LIKE :q ESCAPE '\\' OR p.sku LIKE :q ESCAPE '\\')";
            $params['q'] = '%' . addcslashes($f['q'], '%_\\') . '%';
        }
        if (isset($f['category_id'])) {
            $where[] = 'p.category_id = :category_id';
            $params['category_id'] = $f['category_id'];
        }
        if (isset($f['min_price'])) {
            $where[] = 'p.price_cents >= :min_price';
            $params['min_price'] = $f['min_price'];
        }
        if (isset($f['max_price'])) {
            $where[] = 'p.price_cents <= :max_price';
            $params['max_price'] = $f['max_price'];
        }
        if (isset($f['active'])) {
            $where[] = 'p.active = :active';
            $params['active'] = $f['active'] ? 1 : 0;
        }
        if (isset($f['in_stock'])) {
            $where[] = $f['in_stock'] ? 'p.stock > 0' : 'p.stock = 0';
        }
        $whereSql = $where !== [] ? ' WHERE ' . implode(' AND ', $where) : '';

        $count = $this->pdo->prepare('SELECT COUNT(*) FROM products p' . $whereSql);
        $count->execute($params);

        $stmt = $this->pdo->prepare(self::SELECT . $whereSql . ' ORDER BY ' . $query->orderBy('p') . ' LIMIT :limit OFFSET :offset');
        foreach ($params as $k => $val) {
            $stmt->bindValue($k, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue('limit', $query->perPage, PDO::PARAM_INT);
        $stmt->bindValue('offset', $query->offset(), PDO::PARAM_INT);
        $stmt->execute();

        return ['items' => $stmt->fetchAll(), 'total' => (int) $count->fetchColumn()];
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(self::SELECT . ' WHERE p.id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function skuTaken(string $sku, ?int $exceptId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM products WHERE sku = :sku AND id IS NOT :id');
        $stmt->execute(['sku' => $sku, 'id' => $exceptId]);

        return $stmt->fetchColumn() !== false;
    }

    /** @param array<string, mixed> $data complete, validated product */
    public function create(array $data, string $now): int
    {
        $this->pdo->prepare(
            'INSERT INTO products (sku, name, description, price_cents, stock, active, category_id, created_at, updated_at)
             VALUES (:sku, :name, :description, :price_cents, :stock, :active, :category_id, :created_at, :updated_at)'
        )->execute([
            'sku' => $data['sku'], 'name' => $data['name'], 'description' => $data['description'],
            'price_cents' => $data['price_cents'], 'stock' => $data['stock'], 'active' => $data['active'] ? 1 : 0,
            'category_id' => $data['category_id'], 'created_at' => $now, 'updated_at' => $now,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data only the columns to change (keys come from the validator) */
    public function update(int $id, array $data, string $now): void
    {
        $data = array_intersect_key($data, array_flip(ProductValidator::FIELDS));
        if (array_key_exists('active', $data)) {
            $data['active'] = $data['active'] ? 1 : 0;
        }
        $sets = array_map(static fn (string $col): string => "{$col} = :{$col}", array_keys($data));
        $sets[] = 'updated_at = :updated_at';
        $this->pdo->prepare('UPDATE products SET ' . implode(', ', $sets) . ' WHERE id = :id')
            ->execute($data + ['updated_at' => $now, 'id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->pdo->prepare('DELETE FROM products WHERE id = :id')->execute(['id' => $id]);
    }
}
