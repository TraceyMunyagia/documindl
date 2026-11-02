<?php

use App\Enums\DocumentType;
use App\Extraction\KeywordClassifier;
use App\Extraction\RegexInvoiceExtractor;

it('extracts every field from a complete invoice', function () {
    $result = new RegexInvoiceExtractor()->extract(fixtureText('invoice-001.txt'), DocumentType::Invoice);

    expect($result->supplier)->toBe('Savannah Office Supplies Ltd')
        ->and($result->documentNumber)->toBe('INV-2026-0142')
        ->and($result->issueDate?->format('Y-m-d'))->toBe('2026-10-01')
        ->and($result->dueDate?->format('Y-m-d'))->toBe('2026-10-31')
        ->and($result->tax?->format())->toBe('KES 1,884.16')
        ->and($result->total?->format())->toBe('KES 13,660.16')
        ->and($result->isComplete())->toBeTrue();
});

it('reports missing fields on an incomplete invoice', function () {
    $result = new RegexInvoiceExtractor()->extract(fixtureText('invoice-002-incomplete.txt'), DocumentType::Invoice);

    expect($result->missingFields())->toBe(['document_number', 'due_date']);
});

it('reads receipts from CSV without requiring a due date', function () {
    $result = new RegexInvoiceExtractor()->extract(fixtureText('receipt-001.csv'), DocumentType::Receipt);

    expect($result->total?->format())->toBe('KES 1,250.00')
        ->and($result->isComplete())->toBeTrue();
});

it('classifies documents by keywords', function (string $file, DocumentType $expected) {
    expect(new KeywordClassifier()->classify(fixtureText($file)))->toBe($expected);
})->with([
    'invoice' => ['invoice-001.txt', DocumentType::Invoice],
    'receipt' => ['receipt-001.csv', DocumentType::Receipt],
]);
