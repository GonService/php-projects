<?php

declare(strict_types=1);

namespace ProductsApi\Domain;

use PDO;

final class CategoryRepository
{
    public const SORTABLE = ['name', 'slug', 'created_at', 'id'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array{items: list<array<string, mixed>>, total: int} */
    public function paginate(ListQuery $query): array
    {
        $where = '';
        $params = [];
        if (isset($query->filters['q'])) {
            $where = " WHERE c.name LIKE :q ESCAPE '\\'";
            $params['q'] = '%' . addcslashes($query->filters['q'], '%_\\') . '%';
        }
        $count = $this->pdo->prepare("SELECT COUNT(*) FROM categories c{$where}");
        $count->execute($params);

        $stmt = $this->pdo->prepare(
            'SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count
             FROM categories c' . $where . ' ORDER BY ' . $query->orderBy('c') . ' LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $k => $val) {
            $stmt->bindValue($k, $val);
        }
        $stmt->bindValue('limit', $query->perPage, PDO::PARAM_INT);
        $stmt->bindValue('offset', $query->offset(), PDO::PARAM_INT);
        $stmt->execute();

        return ['items' => $stmt->fetchAll(), 'total' => (int) $count->fetchColumn()];
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count
             FROM categories c WHERE c.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function slugTaken(string $slug, ?int $exceptId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM categories WHERE slug = :s AND id IS NOT :id');
        $stmt->execute(['s' => $slug, 'id' => $exceptId]);

        return $stmt->fetchColumn() !== false;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, string $now): int
    {
        $this->pdo->prepare(
            'INSERT INTO categories (name, slug, description, created_at, updated_at)
             VALUES (:name, :slug, :description, :now, :now2)'
        )->execute([
            'name' => $data['name'], 'slug' => $data['slug'], 'description' => $data['description'] ?? '',
            'now' => $now, 'now2' => $now,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data only the columns to change (keys come from the validator) */
    public function update(int $id, array $data, string $now): void
    {
        $data = array_intersect_key($data, array_flip(CategoryValidator::FIELDS));
        $sets = array_map(static fn (string $col): string => "{$col} = :{$col}", array_keys($data));
        $sets[] = 'updated_at = :updated_at';
        $this->pdo->prepare('UPDATE categories SET ' . implode(', ', $sets) . ' WHERE id = :id')
            ->execute($data + ['updated_at' => $now, 'id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->pdo->prepare('DELETE FROM categories WHERE id = :id')->execute(['id' => $id]);
    }
}
