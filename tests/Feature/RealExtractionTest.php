<?php

use App\Actions\Documents\UploadDocument;
use App\Enums\ExtractionMethod;
use App\Models\Business;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/*
 * Runs the real pdftotext and tesseract against the sample files.
 * Skipped automatically if the tools aren't installed (e.g. on a CI server).
 * Run just these with: php artisan test --group=integration
 */

beforeEach(function () {
    Process::preventStrayProcesses(false);

    foreach (['pdftotext', 'tesseract'] as $tool) {
        if (! Process::run(['which', config("documind.extraction.{$tool}")])->successful()) {
            $this->markTestSkipped("{$tool} is not installed.");
        }
    }

    Storage::fake('documents');
    $this->business = Business::factory()->create();
    $this->actingAs($this->business->owner);
});

function uploadSample(Business $business, string $name)
{
    $file = new UploadedFile(base_path("tests/Fixtures/samples/{$name}"), $name, null, null, true);

    return app(UploadDocument::class)->handle($file, $business->owner, $business);
}

test('the sample PDF is read from its text layer', function () {
    $document = uploadSample($this->business, 'invoice-001.pdf');
    processNow($document);
    $document->refresh();

    expect($document->extraction_method)->toBe(ExtractionMethod::PdfText)
        ->and($document->extracted_text)->toContain('INV-2026-0142')
        ->and($document->extracted_text)->toContain('13,660.16');
})->group('integration');

test('the sample image is read with OCR', function () {
    $document = uploadSample($this->business, 'receipt-scan.jpg');
    processNow($document);
    $document->refresh();

    expect($document->extraction_method)->toBe(ExtractionMethod::Ocr)
        ->and(strtolower((string) $document->extracted_text))->toContain('invoice');
})->group('integration');
