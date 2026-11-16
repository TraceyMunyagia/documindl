<?php

use App\Enums\BusinessRole;
use App\Jobs\ProcessDocument;
use App\Models\Business;
use App\Models\Document;
use App\Models\User;
use App\Parsing\CsvReceiptParser;
use App\Parsing\ParserRegistry;
use App\Parsing\PlainTextParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function fixturePath(string $file): string
{
    return __DIR__.'/Fixtures/samples/'.$file;
}

function fixtureText(string $file): string
{
    return new ParserRegistry()
        ->register(new PlainTextParser)
        ->register(new CsvReceiptParser)
        ->for(pathinfo($file, PATHINFO_EXTENSION))
        ->parse(fixturePath($file));
}
function fakePdf(string $name = 'invoice.pdf', string $body = 'Total: KES 100.00'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n{$body}\n%%EOF\n");
}
function memberOf(Business $business, BusinessRole $role): User
{
    $user = User::factory()->create();
    $business->members()->attach($user->id, ['role' => $role->value]);
    $user->switchBusiness($business);

    return $user;
}

/**
 * Runs ProcessDocument right now, exactly as a queue worker would (dependencies injected).
 * TestCase fakes the queue for ProcessDocument, and that fake also swallows dispatchSync(),
 * so tests call this instead.
 */
function processNow(Document $document): void
{
    app()->call([new ProcessDocument($document), 'handle']);
}
/** A complete, valid extraction payload, with optional overrides. */
function geminiPayload(array $overrides = []): array
{
    return array_replace([
        'document_type' => 'invoice',
        'supplier_name' => 'Savannah Office Supplies Ltd',
        'document_number' => 'INV-2026-0142',
        'issue_date' => '2026-10-01',
        'due_date' => '2026-10-31',
        'currency' => 'KES',
        'total_amount' => '13660.16',
        'tax_amount' => '1884.16',
        'summary' => 'Invoice for paper and printer toner.',
        'confidence' => [
            'supplier_name' => 0.98, 'document_number' => 0.97, 'issue_date' => 0.95, 'due_date' => 0.95,
            'currency' => 0.99, 'total_amount' => 0.97, 'tax_amount' => 0.92,
        ],
        'warnings' => [],
    ], $overrides);
}

/** A Gemini HTTP response wrapping a payload. */
function geminiReply(array $payload, int $promptTokens = 1200, int $outputTokens = 150)
{
    return Http::response([
        'candidates' => [[
            'content' => ['role' => 'model', 'parts' => [['text' => json_encode($payload)]]],
            'finishReason' => 'STOP',
        ]],
        'usageMetadata' => ['promptTokenCount' => $promptTokens, 'candidatesTokenCount' => $outputTokens],
        'modelVersion' => 'gemini-test-model',
    ]);
}

/** The invoice text the payload above describes. */
function sampleInvoiceText(): string
{
    return "INVOICE\nSupplier: Savannah Office Supplies Ltd\nInvoice No: INV-2026-0142\nDate: 2026-10-01\n"
        ."Due Date: 2026-10-31\nVAT (16%): KES 1,884.16\nTotal: KES 13,660.16\n";
}
