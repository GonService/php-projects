<?php

declare(strict_types=1);

namespace ProductsApi\Domain;

use ProductsApi\Http\ApiException;

final class CategoryValidator
{
    public const FIELDS = ['name', 'slug', 'description'];

    public function __construct(private readonly CategoryRepository $categories)
    {
    }

    /**
     * @param array<string, mixed>      $input
     * @param array<string, mixed>|null $current
     * @return array<string, mixed>
     */
    public function validate(array $input, ?array $current = null, bool $partial = false): array
    {
        $v = new Validation();
        $v->rejectUnknown($input, self::FIELDS);
        if ($partial && $input === []) {
            throw ApiException::validation(['body' => ['Send at least one field to change.']]);
        }

        if (!$partial) {
            if (!array_key_exists('name', $input)) {
                $v->add('name', 'This field is required.');
            }
            $input += ['description' => ''];
        }

        $data = [];
        if (array_key_exists('name', $input) && !$v->has('name')) {
            $data['name'] = $v->string($input['name'], 'name', 2, 80);
        }
        if (array_key_exists('description', $input) && !$v->has('description')) {
            $data['description'] = $v->string($input['description'], 'description', 0, 500);
        }
        if (array_key_exists('slug', $input) && !$v->has('slug')) {
            $slug = $input['slug'];
            if (!is_string($slug) || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1 || strlen($slug) > 80) {
                $v->add('slug', 'Use lowercase letters, digits and single dashes (max 80).');
            } else {
                $data['slug'] = $slug;
            }
        } elseif (!$partial && isset($data['name']) && is_string($data['name'])) {
            $data['slug'] = self::slugify($data['name']);
        }
        if (isset($data['slug']) && $this->categories->slugTaken($data['slug'], $current !== null ? (int) $current['id'] : null)) {
            $v->add('slug', 'Another category already uses this slug.');
        }

        if ($v->errors() !== []) {
            throw ApiException::validation($v->errors());
        }

        return $data;
    }

    public static function slugify(string $text): string
    {
        $map = [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'é' => 'e', 'ê' => 'e', 'è' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'ñ' => 'n',
        ];
        $slug = strtr(mb_strtolower($text), $map);
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', $slug), '-');

        return substr($slug, 0, 80) ?: 'category';
    }
}
