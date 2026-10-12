<?php

declare(strict_types=1);

namespace Modernized\Customers;

/** Business rules of the page: what a valid customer is, and the use cases the page offers. */
final class CustomerService
{
    /** @param \Closure(): \DateTimeImmutable $clock */
    public function __construct(
        private readonly CustomerRepository $customers,
        private readonly \Closure $clock,
    ) {
    }

    /** @return list<Customer> */
    public function search(string $term): array
    {
        return $this->customers->search(mb_substr(trim($term), 0, 100));
    }

    /**
     * @param array<string, mixed> $input raw POST data
     * @throws ValidationException
     */
    public function register(array $input): Customer
    {
        $customer = $this->validate($input);
        $id = $this->customers->add($customer, ($this->clock)()->format('Y-m-d H:i:s'));

        return $this->customers->find($id) ?? throw new \LogicException('Customer vanished after insert.');
    }

    public function remove(int $id): bool
    {
        return $this->customers->delete($id);
    }

    /**
     * @param array<string, mixed> $input
     * @throws ValidationException
     */
    public function validate(array $input): NewCustomer
    {
        $get = static fn (string $key): string => is_string($input[$key] ?? null) ? trim($input[$key]) : '';
        $clean = ['name' => $get('name'), 'email' => mb_strtolower($get('email')), 'city' => $get('city')];
        $errors = [];

        $nameLength = mb_strlen($clean['name']);
        if ($nameLength < 2 || $nameLength > 120) {
            $errors['name'] = 'Name must have between 2 and 120 characters.';
        }
        if ($clean['email'] === '' || filter_var($clean['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Enter a valid e-mail address.';
        }
        if (mb_strlen($clean['city']) > 80) {
            $errors['city'] = 'City must have at most 80 characters.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors, $clean);
        }

        return new NewCustomer($clean['name'], $clean['email'], $clean['city']);
    }
}
