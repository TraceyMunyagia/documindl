<?php

use App\Enums\ExtractionMethod;
use App\Exceptions\TextExtractionFailedException;
use App\Extraction\Text\DocumentTextExtractor;
use App\Models\Business;
use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('documents');
    $this->business = Business::factory()->create();
    $this->actingAs($this->business->owner);
});

function storedDocument(Business $business, UploadedFile $file, string $mime): Document
{
    $path = $file->store("businesses/{$business->id}/documents", 'documents');

    return Document::factory()->for($business)->create([
        'original_name' => $file->getClientOriginalName(),
        'disk' => 'documents',
        'path' => $path,
        'mime_type' => $mime,
    ]);
}

function commandContains(string $needle): Closure
{
    return fn (PendingProcess $process): bool => str_contains(implode(' ', (array) $process->command), $needle);
}

test('digital PDFs use the text layer', function () {
    Process::fake(['*pdftotext*' => Process::result(str_repeat("INVOICE Savannah Office Supplies KES 13,660.16\n", 3))]);

    $document = storedDocument($this->business, fakePdf(), 'application/pdf');
    $result = app(DocumentTextExtractor::class)->extract($document);

    expect($result->method)->toBe(ExtractionMethod::PdfText)
        ->and($result->text)->toContain('Savannah');

    Process::assertRan(commandContains('pdftotext'));
    Process::assertDidntRun(commandContains('pdftoppm'));
});

test('PDFs without a text layer fall back to OCR', function () {
    Process::fake([
        '*pdftotext*' => Process::result("   \n  "),
        '*pdftoppm*' => Process::result(''),
    ]);

    $document = storedDocument($this->business, fakePdf(), 'application/pdf');
    $result = app(DocumentTextExtractor::class)->extract($document);

    expect($result->method)->toBe(ExtractionMethod::Ocr);

    Process::assertRan(commandContains('pdftoppm'));
});

test('images go straight to OCR', function () {
    Process::fake(['*tesseract*' => Process::result("RECEIPT\nJava House Kilimani\nTotal KES 1,250.00")]);

    $document = storedDocument($this->business, UploadedFile::fake()->image('receipt.jpg'), 'image/jpeg');
    $result = app(DocumentTextExtractor::class)->extract($document);

    expect($result->method)->toBe(ExtractionMethod::Ocr)
        ->and($result->text)->toContain('Java House');

    Process::assertDidntRun(commandContains('pdftotext'));
});

test('a missing tool gives a readable error', function () {
    Process::fake(['*pdftotext*' => Process::result('', 'command not found', 127)]);

    $document = storedDocument($this->business, fakePdf(), 'application/pdf');

    expect(fn () => app(DocumentTextExtractor::class)->extract($document))
        ->toThrow(TextExtractionFailedException::class, 'missing pdftotext');
});

test('documents without a file cannot be extracted', function () {
    $document = Document::factory()->for($this->business)->create(['path' => null]);

    expect(fn () => app(DocumentTextExtractor::class)->extract($document))
        ->toThrow(TextExtractionFailedException::class, 'no file');
});

test('text is cleaned before it is stored', function () {
    $dirty = "Line one\0  \r\nLine two\x07\n\n\n\n\nLine three\xC3\x28   ";

    expect(DocumentTextExtractor::clean($dirty))
        ->toBe("Line one\nLine two\n\nLine three?(")
        ->and(mb_check_encoding(DocumentTextExtractor::clean($dirty), 'UTF-8'))->toBeTrue();
});
