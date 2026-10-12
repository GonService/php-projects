<?php

declare(strict_types=1);

namespace Modernized\Customers;

use PDO;

/**
 * All SQL for the customers page lives here, and every value goes through a placeholder.
 * Compare with before/clientes.php, where user input was concatenated into the SQL.
 */
final class CustomerRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<Customer> */
    public function search(string $term, int $limit = 200): array
    {
        // The search term is data, not SQL: wildcards in it are escaped and it is bound as a value.
        $stmt = $this->pdo->prepare(
            "SELECT id, nome, email, cidade, criado_em FROM clientes
             WHERE nome LIKE :term ESCAPE '\\' ORDER BY nome COLLATE NOCASE LIMIT :limit"
        );
        $stmt->bindValue('term', '%' . addcslashes($term, '%_\\') . '%');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(Customer::fromRow(...), $stmt->fetchAll());
    }

    public function add(NewCustomer $customer, string $createdAt): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO clientes (nome, email, cidade, criado_em) VALUES (:name, :email, :city, :created_at)'
        );
        $stmt->execute([
            'name' => $customer->name,
            'email' => $customer->email,
            'city' => $customer->city,
            'created_at' => $createdAt,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function find(int $id): ?Customer
    {
        $stmt = $this->pdo->prepare('SELECT id, nome, email, cidade, criado_em FROM clientes WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : Customer::fromRow($row);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM clientes WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() === 1;
    }

    public function count(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM clientes')->fetchColumn();
    }
}
