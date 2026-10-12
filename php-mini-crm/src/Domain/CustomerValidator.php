<?php

declare(strict_types=1);

namespace MiniCrm\Domain;

final class CustomerValidator
{
    public function __construct(private readonly CustomerRepository $customers)
    {
    }

    /**
     * @param array<string, mixed> $input raw form input
     * @return array{data: array<string, string>, errors: array<string, string>}
     */
    public function validate(array $input, ?int $customerId = null): array
    {
        $get = static fn (string $key): string => is_string($input[$key] ?? null) ? trim($input[$key]) : '';
        $errors = [];

        $data = [
            'name' => $get('name'),
            'document' => $get('document'),
            'contact_name' => $get('contact_name'),
            'email' => mb_strtolower($get('email')),
            'phone' => $get('phone'),
            'city' => $get('city'),
            'status' => $get('status'),
        ];

        if ($data['name'] === '') {
            $errors['name'] = 'Name is required.';
        } elseif (mb_strlen($data['name']) > 120) {
            $errors['name'] = 'Name must have at most 120 characters.';
        }

        $document = Document::tryParse($data['document']);
        if ($document === null) {
            $errors['document'] = 'Enter a valid CPF or CNPJ.';
        } else {
            $data['document'] = $document->value;
            if ($this->customers->documentTaken($document->value, $customerId)) {
                $errors['document'] = 'Another customer already uses this document.';
            }
        }

        if ($data['email'] !== '' && filter_var($data['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Enter a valid e-mail address.';
        }
        if ($data['phone'] !== '' && preg_match('/^[0-9 ()+\-]{8,20}$/', $data['phone']) !== 1) {
            $errors['phone'] = 'Use digits, spaces, parentheses or dashes (8 to 20 characters).';
        }
        foreach (['contact_name' => 120, 'city' => 80] as $field => $max) {
            if (mb_strlen($data[$field]) > $max) {
                $errors[$field] = "At most {$max} characters.";
            }
        }
        if (CustomerStatus::tryFrom($data['status']) === null) {
            $errors['status'] = 'Choose a status.';
        }

        return ['data' => $data, 'errors' => $errors];
    }
}
