<?php

declare(strict_types=1);

namespace MiniCrm\Domain;

/**
 * Brazilian taxpayer document: CPF (people, 11 digits) or CNPJ (companies, 14 characters).
 *
 * CNPJ supports the alphanumeric format introduced in July 2026: the first 12 positions may be
 * A-Z or 0-9, the two check digits stay numeric, and each character is worth ord($c) - 48.
 */
final readonly class Document
{
    private function __construct(public DocumentType $type, public string $value)
    {
    }

    /** Returns null when the input is not a valid CPF or CNPJ. */
    public static function tryParse(string $input): ?self
    {
        $clean = strtoupper((string) preg_replace('/[.\-\/\s]/', '', $input));

        if (preg_match('/^\d{11}$/', $clean) === 1 && self::isValidCpf($clean)) {
            return new self(DocumentType::Cpf, $clean);
        }
        if (preg_match('/^[0-9A-Z]{12}\d{2}$/', $clean) === 1 && self::isValidCnpj($clean)) {
            return new self(DocumentType::Cnpj, $clean);
        }

        return null;
    }

    public function formatted(): string
    {
        $v = $this->value;

        return match ($this->type) {
            DocumentType::Cpf => sprintf('%s.%s.%s-%s', substr($v, 0, 3), substr($v, 3, 3), substr($v, 6, 3), substr($v, 9, 2)),
            DocumentType::Cnpj => sprintf('%s.%s.%s/%s-%s', substr($v, 0, 2), substr($v, 2, 3), substr($v, 5, 3), substr($v, 8, 4), substr($v, 12, 2)),
        };
    }

    /** Formats a stored value; falls back to the raw value if it is not a valid document. */
    public static function format(string $value): string
    {
        return self::tryParse($value)?->formatted() ?? $value;
    }

    /** Check digits for the first 9 digits of a CPF. */
    public static function cpfCheckDigits(string $base): string
    {
        $d1 = self::mod11($base, range(10, 2));
        $d2 = self::mod11($base . $d1, range(11, 2));

        return $d1 . $d2;
    }

    /** Check digits for the first 12 characters of a CNPJ (numeric or alphanumeric). */
    public static function cnpjCheckDigits(string $base): string
    {
        $d1 = self::mod11($base, [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
        $d2 = self::mod11($base . $d1, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);

        return $d1 . $d2;
    }

    private static function isValidCpf(string $cpf): bool
    {
        // 000.000.000-00, 111.111.111-11... pass the arithmetic but are not real documents.
        if (preg_match('/^(\d)\1{10}$/', $cpf) === 1) {
            return false;
        }

        return substr($cpf, 9) === self::cpfCheckDigits(substr($cpf, 0, 9));
    }

    private static function isValidCnpj(string $cnpj): bool
    {
        if (preg_match('/^(\d)\1{13}$/', $cnpj) === 1) {
            return false;
        }

        return substr($cnpj, 12) === self::cnpjCheckDigits(substr($cnpj, 0, 12));
    }

    /** @param list<int> $weights */
    private static function mod11(string $chars, array $weights): int
    {
        $sum = 0;
        foreach ($weights as $i => $weight) {
            $sum += (ord($chars[$i]) - 48) * $weight;
        }
        $rest = $sum % 11;

        return $rest < 2 ? 0 : 11 - $rest;
    }
}
