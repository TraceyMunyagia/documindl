<?php

use App\Enums\BusinessRole;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\InvoiceStatus;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Category;
use App\Models\Document;
use App\Models\DocumentExtraction;
use App\Models\Invoice;
use Livewire\Livewire;

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->owner = $this->business->owner;
    $this->actingAs($this->owner);

    $this->document = Document::factory()->for($this->business)->reviewRequired()->create([
        'uploaded_by' => $this->owner->id,
        'created_at' => now()->subHour(),
    ]);
    $this->extraction = aiExtraction($this->document);
});

function reviewPage(Document $document)
{
    return Livewire::test('pages::review.show', ['document' => $document]);
}

test('the form is prefilled from the AI extraction', function () {
    reviewPage($this->document)
        ->assertSet('form.type', 'invoice')
        ->assertSet('form.supplier', 'Savannah Office Supplies Ltd')
        ->assertSet('form.total', '13660.16')
        ->assertSet('form.tax', '1884.16')
        ->assertSet('form.dueDate', '2026-10-31')
        ->assertSee('Low confidence – check'); // tax confidence is 0.5, below the 0.6 "medium" threshold
});

test('verifying creates a pending invoice and records what changed', function () {
    reviewPage($this->document)
        ->set('form.supplier', 'Savannah Office Supplies Limited')
        ->set('form.total', ' 13,660.16 ')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('review.index'));

    $invoice = Invoice::sole();
    $extraction = $this->extraction->fresh();

    expect($invoice->supplier_name)->toBe('Savannah Office Supplies Limited')
        ->and($invoice->total->amountInCents)->toBe(1366016)
        ->and($invoice->status)->toBe(InvoiceStatus::Pending)
        ->and($invoice->document_id)->toBe($this->document->id)
        ->and($this->document->fresh()->status)->toBe(DocumentStatus::Completed)
        ->and($extraction->verified_at)->not->toBeNull()
        ->and($extraction->verified_by)->toBe($this->owner->id)
        ->and(array_keys($extraction->corrections))->toBe(['supplier_name'])
        ->and($extraction->corrections['supplier_name']['ai'])->toBe('Savannah Office Supplies Ltd')
        ->and($extraction->review_seconds)->not->toBeNull()
        ->and($extraction->fields['supplier_name'])->toBe('Savannah Office Supplies Ltd') // AI answer kept
        ->and(AuditLog::where('action', 'document.verified')->exists())->toBeTrue();
});

test('changing only capitals or spacing is not a correction', function () {
    reviewPage($this->document)
        ->set('form.supplier', 'SAVANNAH   OFFICE SUPPLIES LTD')
        ->call('save');

    expect($this->extraction->fresh()->corrections)->toBe([]);
});

test('receipts are saved as already paid', function () {
    reviewPage($this->document)
        ->set('form.type', 'receipt')
        ->call('save')
        ->assertHasNoErrors();

    $invoice = Invoice::sole();

    expect($invoice->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->paid_at->format('Y-m-d'))->toBe('2026-10-01')
        ->and($invoice->due_date)->toBeNull()
        ->and($this->document->fresh()->type)->toBe(DocumentType::Receipt)
        ->and($this->extraction->fresh()->corrections)->toHaveKeys(['document_type', 'due_date']);
});

test('invalid values are rejected and nothing is saved', function (string $field, string $value) {
    reviewPage($this->document)
        ->set("form.{$field}", $value)
        ->call('save')
        ->assertHasErrors("form.{$field}");

    expect(Invoice::count())->toBe(0)
        ->and($this->document->fresh()->status)->toBe(DocumentStatus::ReviewRequired);
})->with([
    'no supplier' => ['supplier', ''],
    'no number' => ['documentNumber', ''],
    'text as total' => ['total', 'abc'],
    'zero total' => ['total', '0.00'],
    'tax over total' => ['tax', '20000'],
    'due before issue' => ['dueDate', '2026-09-01'],
    'invoice without due date' => ['dueDate', ''],
    'wrong date format' => ['issueDate', '01/10/2026'],
    'bad currency' => ['currency', 'shillings'],
]);

test('a category from another business is rejected', function () {
    $foreign = Category::factory()->for(Business::factory())->create();

    reviewPage($this->document)
        ->set('form.categoryId', (string) $foreign->id)
        ->call('save')
        ->assertHasErrors('form.categoryId');
});

test('documents with no extraction can be entered by hand', function () {
    $manual = Document::factory()->for($this->business)->reviewRequired()->create(['uploaded_by' => $this->owner->id]);

    reviewPage($manual)
        ->assertSee('please type the details in')
        ->set('form.supplier', 'Mlimani Electricals')
        ->set('form.documentNumber', 'ME-77')
        ->set('form.issueDate', '2026-11-01')
        ->set('form.dueDate', '2026-11-15')
        ->set('form.total', '5800')
        ->call('save')
        ->assertHasNoErrors();

    $extraction = DocumentExtraction::where('document_id', $manual->id)->sole();

    expect($extraction->provider)->toBe('manual')
        ->and($extraction->corrections)->toBe([])
        ->and(Invoice::where('document_id', $manual->id)->sole()->total->format())->toBe('KES 5,800.00');
});

test('if someone else saves first, your save is refused politely', function () {
    $component = reviewPage($this->document);

    $this->document->forceFill(['status' => DocumentStatus::Completed])->save(); // the other reviewer won

    $component->call('save')->assertRedirect(route('documents.show', $this->document));

    expect(Invoice::count())->toBe(0);
});

test('verify and next goes to the next waiting document', function () {
    $next = Document::factory()->for($this->business)->reviewRequired()->create(['uploaded_by' => $this->owner->id]);

    reviewPage($this->document)
        ->call('saveAndNext')
        ->assertRedirect(route('documents.review', $next));
});

test('documents that turn out not to be financial are completed without an invoice', function () {
    reviewPage($this->document)
        ->call('markAs', 'contract')
        ->assertRedirect(route('review.index'));

    expect($this->document->fresh()->status)->toBe(DocumentStatus::Completed)
        ->and($this->document->fresh()->type)->toBe(DocumentType::Contract)
        ->and(Invoice::count())->toBe(0)
        ->and($this->extraction->fresh()->corrections)->toHaveKey('document_type');
});

test('documents that are not waiting for review redirect to their page', function () {
    $done = Document::factory()->for($this->business)->create(['status' => DocumentStatus::Completed]);

    reviewPage($done)->assertRedirect(route('documents.show', $done));
});

test('employees can review their own uploads only', function () {
    $employee = memberOf($this->business, BusinessRole::Employee);
    $own = Document::factory()->for($this->business)->reviewRequired()->create(['uploaded_by' => $employee->id]);

    Livewire::actingAs($employee)
        ->test('pages::review.show', ['document' => $this->document])
        ->assertForbidden();

    Livewire::actingAs($employee)
        ->test('pages::review.show', ['document' => $own])
        ->assertOk();
});
