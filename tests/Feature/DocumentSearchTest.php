<?php

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Business;
use App\Models\Category;
use App\Models\Document;
use App\Models\Invoice;
use App\Queries\DocumentIndexQuery;
use App\Support\DocumentFilters;

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->actingAs($this->business->owner);
});

function documentNames(array $input): array
{
    return app(DocumentIndexQuery::class)
        ->build(DocumentFilters::fromInput($input))
        ->pluck('original_name')
        ->all();
}

test('search matches names case-insensitively', function () {
    Document::factory()->for($this->business)->create(['original_name' => 'Fuel-Receipt-March.pdf']);
    Document::factory()->for($this->business)->create(['original_name' => 'airtime.pdf']);

    expect(documentNames(['search' => 'fuel']))->toBe(['Fuel-Receipt-March.pdf']);
});

test('search finds documents through their invoice supplier and number', function () {
    $document = Document::factory()->for($this->business)->create(['original_name' => 'scan-0001.pdf']);
    Invoice::factory()->for($this->business)->for($document)->create([
        'supplier_name' => 'Nyali Packaging Ltd',
        'invoice_number' => 'NP-7788',
    ]);
    Document::factory()->for($this->business)->create(['original_name' => 'other.pdf']);

    expect(documentNames(['search' => 'nyali']))->toBe(['scan-0001.pdf'])
        ->and(documentNames(['search' => 'NP-77']))->toBe(['scan-0001.pdf']);
});

test('wildcard characters in search are treated literally', function () {
    Document::factory()->for($this->business)->create(['original_name' => '50% deposit.pdf']);
    Document::factory()->for($this->business)->create(['original_name' => '500 receipt.pdf']);

    expect(documentNames(['search' => '50%']))->toBe(['50% deposit.pdf']);
});

test('type, status and category filters combine', function () {
    $transport = Category::factory()->for($this->business)->create();

    Document::factory()->for($this->business)->create([
        'original_name' => 'match.pdf', 'type' => DocumentType::Receipt,
        'status' => DocumentStatus::Completed, 'category_id' => $transport->id,
    ]);
    Document::factory()->for($this->business)->create([
        'original_name' => 'wrong-type.pdf', 'type' => DocumentType::Invoice,
        'status' => DocumentStatus::Completed, 'category_id' => $transport->id,
    ]);
    Document::factory()->for($this->business)->create([
        'original_name' => 'uncategorised.pdf', 'type' => DocumentType::Receipt,
        'status' => DocumentStatus::Completed, 'category_id' => null,
    ]);

    expect(documentNames(['type' => 'receipt', 'status' => 'completed', 'category' => (string) $transport->id]))
        ->toBe(['match.pdf'])
        ->and(documentNames(['category' => 'none']))->toBe(['uncategorised.pdf']);
});

test('date filters use Nairobi days, not UTC days', function () {
    // 10:00 UTC on 1 Oct is 13:00 in Nairobi → 1 Oct.
    Document::factory()->for($this->business)->create(['original_name' => 'inside.pdf', 'created_at' => '2026-10-01 10:00:00']);
    // 22:00 UTC on 1 Oct is 01:00 on 2 Oct in Nairobi → outside.
    Document::factory()->for($this->business)->create(['original_name' => 'next-day.pdf', 'created_at' => '2026-10-01 22:00:00']);

    expect(documentNames(['from' => '2026-10-01', 'to' => '2026-10-01']))->toBe(['inside.pdf']);
});

test('invalid filter values are ignored', function () {
    Document::factory()->count(3)->for($this->business)->create();

    $filters = DocumentFilters::fromInput(['status' => 'hacked', 'sort' => 'password', 'from' => '2026-02-31', 'category' => '1 OR 1=1']);

    expect($filters->status)->toBeNull()
        ->and($filters->sort)->toBe('created_at')
        ->and($filters->from)->toBeNull()
        ->and($filters->category)->toBeNull()
        ->and(app(DocumentIndexQuery::class)->build($filters)->count())->toBe(3);
});

test('sorting by name ascending', function () {
    foreach (['charlie.pdf', 'alpha.pdf', 'bravo.pdf'] as $name) {
        Document::factory()->for($this->business)->create(['original_name' => $name]);
    }

    expect(documentNames(['sort' => 'original_name', 'direction' => 'asc']))
        ->toBe(['alpha.pdf', 'bravo.pdf', 'charlie.pdf']);
});
