<?php

declare(strict_types=1);

namespace ProductsApi\Domain;

/** Collects field errors in the shape used by every 422 response: field => list of messages. */
final class Validation
{
    /** @var array<string, list<string>> */
    private array $errors = [];

    public function add(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    public function has(string $field): bool
    {
        return isset($this->errors[$field]);
    }

    /** @return array<string, list<string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Rejects fields the resource does not have, so typos ("prise_cents") are not silently ignored.
     *
     * @param array<string, mixed> $input
     * @param list<string>         $known
     */
    public function rejectUnknown(array $input, array $known, array $readOnly = ['id', 'created_at', 'updated_at']): void
    {
        foreach (array_keys($input) as $field) {
            if (in_array($field, $readOnly, true)) {
                $this->add((string) $field, 'This field is read-only.');
            } elseif (!in_array($field, $known, true)) {
                $this->add((string) $field, 'Unknown field.');
            }
        }
    }

    public function string(mixed $value, string $field, int $min, int $max): ?string
    {
        if (!is_string($value)) {
            $this->add($field, 'Must be a string.');

            return null;
        }
        $value = trim($value);
        $length = mb_strlen($value);
        if ($length < $min) {
            $this->add($field, $min === 1 ? 'Must not be empty.' : "Must have at least {$min} characters.");
        } elseif ($length > $max) {
            $this->add($field, "Must have at most {$max} characters.");
        }

        return $value;
    }

    public function int(mixed $value, string $field, int $min, int $max): ?int
    {
        if (!is_int($value)) {
            $this->add($field, 'Must be an integer.');

            return null;
        }
        if ($value < $min || $value > $max) {
            $this->add($field, "Must be between {$min} and {$max}.");
        }

        return $value;
    }

    public function bool(mixed $value, string $field): ?bool
    {
        if (!is_bool($value)) {
            $this->add($field, 'Must be true or false.');

            return null;
        }

        return $value;
    }
}
