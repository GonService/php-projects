<?php

declare(strict_types=1);

namespace ProductsApi\Domain;

use ProductsApi\Http\ApiException;

/**
 * Pagination, filters and sorting parsed from the query string. Invalid values give a 422
 * instead of being silently ignored, so clients find their mistakes quickly.
 */
final readonly class ListQuery
{
    public const MAX_PER_PAGE = 100;

    /**
     * @param array<string, mixed>       $filters validated filter values
     * @param list<array{string, string}> $sort    [column, ASC|DESC] pairs
     */
    public function __construct(
        public int $page = 1,
        public int $perPage = 20,
        public array $filters = [],
        public array $sort = [['name', 'ASC']],
    ) {
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    /**
     * @param array<string, mixed>          $query    raw $_GET
     * @param array<string, string>         $filterTypes name => int|string|bool
     * @param list<string>                  $sortable columns clients may sort by
     */
    public static function fromQuery(array $query, array $filterTypes, array $sortable, string $defaultSort): self
    {
        $v = new Validation();
        $int = static function (string $name, int $min, int $max, int $default) use ($query, $v): int {
            if (!isset($query[$name]) || $query[$name] === '') {
                return $default;
            }
            if (!is_string($query[$name]) || preg_match('/^\d{1,9}$/', $query[$name]) !== 1
                || (int) $query[$name] < $min || (int) $query[$name] > $max) {
                $v->add($name, "Must be an integer between {$min} and {$max}.");

                return $default;
            }

            return (int) $query[$name];
        };

        $page = $int('page', 1, 1_000_000, 1);
        $perPage = $int('per_page', 1, self::MAX_PER_PAGE, 20);

        $filters = [];
        foreach ($filterTypes as $name => $type) {
            $raw = $query[$name] ?? null;
            if ($raw === null || $raw === '') {
                continue;
            }
            if (!is_string($raw)) {
                $v->add($name, 'Must be a single value.');
                continue;
            }
            switch ($type) {
                case 'int':
                    $filters[$name] = $int($name, 0, PHP_INT_MAX >> 1, 0);
                    break;
                case 'bool':
                    if (!in_array($raw, ['true', 'false', '1', '0'], true)) {
                        $v->add($name, 'Must be true or false.');
                    } else {
                        $filters[$name] = in_array($raw, ['true', '1'], true);
                    }
                    break;
                default:
                    $filters[$name] = mb_substr(trim($raw), 0, 100);
            }
        }

        $sort = [];
        $sortParam = $query['sort'] ?? $defaultSort;
        foreach (explode(',', is_string($sortParam) ? $sortParam : '') as $part) {
            $part = trim($part);
            $column = ltrim($part, '-');
            if (!in_array($column, $sortable, true)) {
                $v->add('sort', 'Sort by one of: ' . implode(', ', $sortable) . ' (prefix with "-" for descending).');
                break;
            }
            $sort[] = [$column, str_starts_with($part, '-') ? 'DESC' : 'ASC'];
        }

        if ($v->errors() !== []) {
            throw new ApiException(422, 'invalid_query', 'Invalid query parameters.', $v->errors());
        }

        return new self($page, $perPage, $filters, $sort);
    }

    /** ORDER BY built only from whitelisted columns, with id as a stable tie-breaker. */
    public function orderBy(string $alias): string
    {
        $parts = array_map(static fn (array $s): string => "{$alias}.{$s[0]} {$s[1]}", $this->sort);
        $parts[] = "{$alias}.id ASC";

        return implode(', ', $parts);
    }
}
