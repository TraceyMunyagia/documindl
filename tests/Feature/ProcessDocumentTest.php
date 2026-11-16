<?php

use App\Actions\Documents\UploadDocument;
use App\Enums\BusinessRole;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\ExtractionMethod;
use App\Jobs\ProcessDocument;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('documents');
    $this->business = Business::factory()->create();
    $this->owner = $this->business->owner;
    $this->actingAs($this->owner);
});

test('uploading a document queues processing', function () {
    $document = app(UploadDocument::class)->handle(fakePdf(), $this->owner, $this->business);

    Queue::assertPushed(ProcessDocument::class, fn (ProcessDocument $job): bool => $job->document->is($document));
});

test('a digital invoice is read, classified and sent for review', function () {
    Process::fake(['*pdftotext*' => Process::result(
        "INVOICE\nSupplier: Savannah Office Supplies Ltd\nInvoice No: INV-2026-0142\nBill To: Kilimani Bakery\nTotal: KES 13,660.16\n"
    )]);

    $document = app(UploadDocument::class)->handle(fakePdf(), $this->owner, $this->business);
    processNow($document);
    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::ReviewRequired)
        ->and($document->type)->toBe(DocumentType::Invoice)
        ->and($document->extraction_method)->toBe(ExtractionMethod::PdfText)
        ->and($document->extracted_text)->toContain('INV-2026-0142')
        ->and($document->processed_at)->not->toBeNull()
        ->and($document->processing_note)->toBeNull()
        ->and(AuditLog::where('action', 'document.processed')->exists())->toBeTrue();
});

test('non-financial documents are completed', function () {
    Process::fake(['*pdftotext*' => Process::result(
        "SERVICE AGREEMENT\nThis agreement is made between Kilimani Bakery Ltd (hereinafter the Client) and Upper Hill Cleaning Services."
    )]);

    $document = app(UploadDocument::class)->handle(fakePdf(), $this->owner, $this->business);
    processNow($document);

    expect($document->fresh()->status)->toBe(DocumentStatus::Completed)
        ->and($document->fresh()->type)->toBe(DocumentType::Contract);
});

test('a type chosen by hand is kept', function () {
    Process::fake(['*pdftotext*' => Process::result(str_repeat("INVOICE Bill To: someone amount due KES 100\n", 2))]);

    $document = app(UploadDocument::class)->handle(fakePdf(), $this->owner, $this->business);
    $document->update(['type' => DocumentType::Receipt]);
    processNow($document);

    expect($document->fresh()->type)->toBe(DocumentType::Receipt);
});

test('unreadable scans need review with a helpful note', function () {
    Process::fake([
        '*pdftotext*' => Process::result(''),
        '*pdftoppm*' => Process::result(''),
    ]);

    $document = app(UploadDocument::class)->handle(fakePdf(), $this->owner, $this->business);
    processNow($document);
    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::ReviewRequired)
        ->and($document->extraction_method)->toBe(ExtractionMethod::Ocr)
        ->and($document->extracted_text)->toBeNull()
        ->and($document->processing_note)->toContain('No readable text');
});

test('images are read with OCR', function () {
    Process::fake(['*tesseract*' => Process::result("RECEIPT\nJava House Kilimani\nPaid with M-Pesa\nTotal KES 1,250.00")]);

    $document = app(UploadDocument::class)->handle(UploadedFile::fake()->image('receipt.jpg'), $this->owner, $this->business);
    processNow($document);
    $document->refresh();

    expect($document->type)->toBe(DocumentType::Receipt)
        ->and($document->extraction_method)->toBe(ExtractionMethod::Ocr);
});

test('permanent errors fail the document with a reason', function () {
    Process::fake(['*pdftotext*' => Process::result('', 'Syntax Error: Couldn\'t find trailer dictionary', 1)]);

    $document = app(UploadDocument::class)->handle(fakePdf(), $this->owner, $this->business);
    processNow($document);
    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::Failed)
        ->and($document->failure_reason)->toContain('could not be read')
        ->and($document->failure_reason)->toContain('trailer dictionary');
});

test('the failed() handler marks the document failed after retries run out', function () {
    $document = app(UploadDocument::class)->handle(fakePdf(), $this->owner, $this->business);

    (new ProcessDocument($document))->failed(new RuntimeException('Worker crashed'));

    expect($document->fresh()->status)->toBe(DocumentStatus::Failed)
        ->and($document->fresh()->failure_reason)->toContain('stopped unexpectedly');
});

test('finished documents are not processed again', function () {
    Process::fake();

    $document = Document::factory()->for($this->business)->create(['status' => DocumentStatus::Completed]);
    processNow($document);

    Process::assertNothingRan();
    expect($document->fresh()->status)->toBe(DocumentStatus::Completed);
});

test('failed documents can be tried again', function () {
    $document = Document::factory()->for($this->business)->create([
        'status' => DocumentStatus::Failed,
        'failure_reason' => 'Something broke',
        'uploaded_by' => $this->owner->id,
    ]);

    Livewire::actingAs($this->owner)
        ->test('pages::documents.show', ['document' => $document])
        ->call('reprocess');

    expect($document->fresh()->status)->toBe(DocumentStatus::Pending)
        ->and($document->fresh()->failure_reason)->toBeNull();

    Queue::assertPushed(ProcessDocument::class);
});

test('employees cannot retry other people\'s documents', function () {
    $employee = memberOf($this->business, BusinessRole::Employee);
    $document = Document::factory()->for($this->business)->create([
        'status' => DocumentStatus::Failed,
        'uploaded_by' => $this->owner->id,
    ]);

    Livewire::actingAs($employee)
        ->test('pages::documents.show', ['document' => $document])
        ->call('reprocess')
        ->assertForbidden();
});
