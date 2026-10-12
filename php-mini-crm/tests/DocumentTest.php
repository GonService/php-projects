<?php

declare(strict_types=1);

use MiniCrm\Domain\Document;
use MiniCrm\Domain\DocumentType;

test('Document accepts a valid CPF with or without punctuation', function (): void {
    $a = Document::tryParse('529.982.247-25');
    $b = Document::tryParse('52998224725');
    assertSame(DocumentType::Cpf, $a?->type);
    assertSame('52998224725', $a->value);
    assertSame('529.982.247-25', $b?->formatted());
});

test('Document rejects a CPF with a wrong check digit', function (): void {
    assertSame(null, Document::tryParse('529.982.247-24'));
    assertSame(null, Document::tryParse('529.982.247-35'));
});

test('Document rejects repeated-digit CPF and CNPJ', function (): void {
    assertSame(null, Document::tryParse('111.111.111-11'));
    assertSame(null, Document::tryParse('00.000.000/0000-00'));
});

test('Document accepts a valid numeric CNPJ', function (): void {
    $doc = Document::tryParse('11.222.333/0001-81');
    assertSame(DocumentType::Cnpj, $doc?->type);
    assertSame('11.222.333/0001-81', $doc->formatted());
});

test('Document rejects a CNPJ with a wrong check digit', function (): void {
    assertSame(null, Document::tryParse('11.222.333/0001-80'));
});

test('Document accepts the alphanumeric CNPJ format', function (): void {
    $doc = Document::tryParse('12.abc.345/01de-35');
    assertSame('12ABC34501DE35', $doc?->value);
    assertSame('12.ABC.345/01DE-35', $doc->formatted());
    assertSame(null, Document::tryParse('12.ABC.345/01DE-36'));
});

test('Document rejects wrong lengths and garbage', function (): void {
    foreach (['', '123', '5299822472', '529982247255', 'abc.def.ghi-jk', "52998224725' OR 1=1"] as $input) {
        assertSame(null, Document::tryParse($input), $input);
    }
});

test('Document check digit helpers generate valid documents', function (): void {
    assertSame('25', Document::cpfCheckDigits('529982247'));
    assertSame('81', Document::cnpjCheckDigits('112223330001'));
});
