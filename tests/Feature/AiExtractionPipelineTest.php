<?php

use App\Actions\Documents\UploadDocument;
use App\Ai\AiUsageGuard;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Business;
use App\Models\Document;
use App\Models\DocumentExtraction;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

beforeEach(function () {
    Storage::fake('documents');
    Sleep::fake();

    config([
        'documind.ai.enabled' => true,
        'documind.ai.pricing.gemini' => ['input_per_million' => 0.30, 'output_per_million' => 2.50], // test values
    ]);

    $this->business = Business::factory()->create();
    $this->owner = $this->business->owner;
    $this->actingAs($this->owner);

    Process::fake(['*pdftotext*' => Process::result(sampleInvoiceText().str_repeat("Line item\n", 5))]);
});

function processUploadedPdf(Business $business): Document
{
    $document = app(UploadDocument::class)->handle(fakePdf(), $business->owner, $business);
    processNow($document);

    return $document->fresh();
}

test('Gemini fields are saved as an unverified extraction', function () {
    Http::fake(['generativelanguage.googleapis.com/*' => geminiReply(geminiPayload(), 1000, 200)]);

    $document = processUploadedPdf($this->business);
    $extraction = $document->latestExtraction;

    expect($document->status)->toBe(DocumentStatus::ReviewRequired)
        ->and($document->type)->toBe(DocumentType::Invoice)
        ->and($extraction->provider)->toBe('gemini')
        ->and($extraction->model)->toBe('gemini-test-model')
        ->and($extraction->field('supplier_name'))->toBe('Savannah Office Supplies Ltd')
        ->and($extraction->field('total_cents'))->toBe(1366016)
        ->and($extraction->missing_fields)->toBe([])
        ->and($extraction->verified_at)->toBeNull()
        ->and((float) $extraction->estimated_cost_usd)->toBe(0.0008); // (1000×0.30 + 200×2.50) / 1M
});

test('the document text is sent between markers, with the instructions separate', function () {
    Http::fake(['*' => geminiReply(geminiPayload())]);

    processUploadedPdf($this->business);

    Http::assertSent(function (Request $request): bool {
        $user = $request['contents'][0]['parts'][0]['text'];
        $system = $request['systemInstruction']['parts'][0]['text'];

        return str_contains($user, "<<<DOCUMENT_START>>>\nINVOICE")
            && str_contains($user, '<<<DOCUMENT_END>>>')
            && str_contains($system, 'untrusted data')
            && ! str_contains($system, 'INV-2026-0142');
    });
});

test('the AI decides the type when the keywords guessed wrong', function () {
    Process::fake(['*pdftotext*' => Process::result(str_repeat("Payment confirmation KES 1,250.00 thank you\n", 3))]);
    Http::fake(['*' => geminiReply(geminiPayload(['document_type' => 'receipt', 'total_amount' => '1250', 'tax_amount' => '']))]);

    $document = processUploadedPdf($this->business);

    expect($document->type)->toBe(DocumentType::Receipt);
});

test('when the AI is unavailable on the last attempt, rules are used with a warning', function () {
    Http::fake(['*' => Http::response(['error' => ['message' => 'slow down']], 429)]);

    $document = processUploadedPdf($this->business);
    $extraction = $document->latestExtraction;

    expect($document->status)->toBe(DocumentStatus::ReviewRequired)
        ->and($extraction->provider)->toBe('rules')
        ->and($extraction->field('document_number'))->toBe('INV-2026-0142')
        ->and($extraction->warnings[0])->toContain('busy right now');
});

test('a bad API key falls back to rules instead of failing the document', function () {
    Http::fake(['*' => Http::response(['error' => ['message' => 'API key not valid']], 400)]);

    $document = processUploadedPdf($this->business);

    expect($document->status)->not->toBe(DocumentStatus::Failed)
        ->and($document->latestExtraction->provider)->toBe('rules');
});

test('the daily limit stops AI calls for that business only', function () {
    config(['documind.ai.daily_calls_per_business' => 1]);
    Http::fake(['*' => geminiReply(geminiPayload())]);

    $guard = app(AiUsageGuard::class);
    $guard->attempt($this->business->id); // use up today's single call

    $document = processUploadedPdf($this->business);

    expect($document->latestExtraction->provider)->toBe('rules')
        ->and($document->latestExtraction->warnings[0])->toContain("Today's AI extraction limit");

    Http::assertNothingSent();
});

test('with AI switched off, no requests are made', function () {
    config(['documind.ai.enabled' => false]);
    Http::fake();

    $document = processUploadedPdf($this->business);

    expect($document->latestExtraction->provider)->toBe('rules');

    Http::assertNothingSent();
});

test('a retry skips text extraction when the text is already saved', function () {
    Http::fake(['*' => geminiReply(geminiPayload())]);

    $document = app(UploadDocument::class)->handle(fakePdf(), $this->owner, $this->business);
    $document->forceFill([
        'status' => DocumentStatus::Processing,
        'extracted_text' => sampleInvoiceText(),
        'extraction_method' => 'pdf_text',
    ])->save();

    Process::fake();
    processNow($document);

    Process::assertNothingRan();
    expect(DocumentExtraction::count())->toBe(1);
});
