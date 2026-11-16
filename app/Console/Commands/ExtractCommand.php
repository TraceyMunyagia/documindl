<?php

namespace App\Console\Commands;

use App\Contracts\InvoiceExtractor;
use App\Enums\DocumentType;
use App\Extraction\RegexInvoiceExtractor;
use App\Models\Document;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ExtractCommand extends Command
{
    protected $signature = 'documind:extract
        {document : The document ID}
        {--rules : Use the rule-based extractor instead of the configured one}';

    protected $description = 'Run field extraction on one document and print the result (nothing is saved)';

    public function handle(): int
    {
        $document = Document::query()->find($this->argument('document'));

        if ($document === null || $document->extracted_text === null) {
            $this->components->error('That document does not exist or has no extracted text yet.');

            return self::FAILURE;
        }

        $extractor = $this->option('rules') ? app(RegexInvoiceExtractor::class) : app(InvoiceExtractor::class);
        $text = Str::limit($document->extracted_text, (int) config('documind.ai.max_input_chars'), '');

        $this->components->info('Extracting with '.class_basename($extractor).'…');
        $result = $extractor->extract($text, $document->type === DocumentType::Other ? DocumentType::Invoice : $document->type);

        $fields = $result->toFields();

        $this->table(['Field', 'Value', 'Confidence'], [
            ['Type', $result->type->label(), '—'],
            ['Supplier', $fields['supplier_name'] ?? '—', $result->confidence['supplier_name'] ?? '—'],
            ['Number', $fields['document_number'] ?? '—', $result->confidence['document_number'] ?? '—'],
            ['Issue date', $fields['issue_date'] ?? '—', $result->confidence['issue_date'] ?? '—'],
            ['Due date', $fields['due_date'] ?? '—', $result->confidence['due_date'] ?? '—'],
            ['Total', $result->total?->format() ?? '—', $result->confidence['total_amount'] ?? '—'],
            ['Tax', $result->tax?->format() ?? '—', $result->confidence['tax_amount'] ?? '—'],
        ]);

        if ($result->summary) {
            $this->line("<options=bold>Summary:</> {$result->summary}");
        }

        foreach ($result->warnings as $warning) {
            $this->components->warn($warning);
        }

        if ($result->missingFields() !== []) {
            $this->components->warn('Missing: '.implode(', ', $result->missingFields()));
        }

        if ($usage = $result->usage) {
            $this->components->twoColumnDetail('Model', (string) $usage->model);
            $this->components->twoColumnDetail('Tokens in / out', "{$usage->promptTokens} / {$usage->outputTokens}");
            $this->components->twoColumnDetail('Latency', "{$usage->latencyMs} ms");
            $this->components->twoColumnDetail('Estimated cost', '$'.number_format($usage->estimatedCostUsd(), 6));
        }

        return self::SUCCESS;
    }
}
