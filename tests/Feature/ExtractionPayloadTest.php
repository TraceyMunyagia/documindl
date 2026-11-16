<?php

use App\Ai\Extraction\ExtractionPayload;
use App\Enums\DocumentType;
use App\Exceptions\AiExtractionException;
use App\Extraction\ExtractionResult;

function parsePayload(array $overrides = [], ?string $text = null): ExtractionResult
{
    return app(ExtractionPayload::class)->toResult(geminiPayload($overrides), $text ?? sampleInvoiceText());
}

test('a clean answer becomes a complete result', function () {
    $result = parsePayload();

    expect($result->type)->toBe(DocumentType::Invoice)
        ->and($result->supplier)->toBe('Savannah Office Supplies Ltd')
        ->and($result->total->amountInCents)->toBe(1366016)
        ->and($result->tax->format())->toBe('KES 1,884.16')
        ->and($result->dueDate->format('Y-m-d'))->toBe('2026-10-31')
        ->and($result->isComplete())->toBeTrue()
        ->and($result->warnings)->toBe([]);
});

test('messy amounts and currency names are normalised', function () {
    $result = parsePayload([
        'currency' => 'Ksh',
        'total_amount' => 'KES 13,660.16',
        'tax_amount' => 1884.16,
    ]);

    expect($result->total->currency)->toBe('KES')
        ->and($result->total->amountInCents)->toBe(1366016)
        ->and($result->tax->amountInCents)->toBe(188416);
});

test('a missing currency is assumed, with a warning', function () {
    $result = parsePayload(['currency' => '']);

    expect($result->total->currency)->toBe('KES')
        ->and($result->warnings)->toContain('No currency was shown, so KES was assumed.')
        ->and($result->confidence['currency'])->toBeLessThanOrEqual(0.5);
});

test('impossible dates are dropped with a warning', function () {
    $result = parsePayload(['due_date' => '2026-02-31']);

    expect($result->dueDate)->toBeNull()
        ->and($result->confidence['due_date'])->toBe(0.0)
        ->and($result->missingFields())->toContain('due_date')
        ->and(implode(' ', $result->warnings))->toContain("Couldn't understand the due date");
});

test('tax larger than the total is flagged', function () {
    $result = parsePayload(['tax_amount' => '20000']);

    expect(implode(' ', $result->warnings))->toContain('tax amount is larger than the total')
        ->and($result->confidence['tax_amount'])->toBeLessThanOrEqual(0.3);
});

test('a due date before the issue date is flagged', function () {
    $result = parsePayload(['due_date' => '2026-09-01']);

    expect(implode(' ', $result->warnings))->toContain('due date is before the issue date');
});

test('a total that is not in the document text is flagged', function () {
    $result = parsePayload(['total_amount' => '99999.99']);

    expect(implode(' ', $result->warnings))->toContain('does not appear in the document text')
        ->and($result->confidence['total_amount'])->toBeLessThanOrEqual(0.4);
});

test('confidence is clamped and zero for empty fields', function () {
    $result = parsePayload([
        'supplier_name' => '',
        'confidence' => array_merge(geminiPayload()['confidence'], ['supplier_name' => 0.9, 'total_amount' => 7]),
    ]);

    expect($result->confidence['supplier_name'])->toBe(0.0)
        ->and($result->confidence['total_amount'])->toBe(1.0);
});

test('answers missing required keys are rejected', function () {
    $payload = geminiPayload();
    unset($payload['total_amount']);

    expect(fn () => app(ExtractionPayload::class)->toResult($payload, sampleInvoiceText()))
        ->toThrow(AiExtractionException::class);
});

test('an unknown document type is rejected', function () {
    expect(fn () => parsePayload(['document_type' => 'bank_statement']))
        ->toThrow(AiExtractionException::class);
});
