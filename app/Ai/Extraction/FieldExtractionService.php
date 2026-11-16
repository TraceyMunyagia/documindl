<?php

declare(strict_types=1);

namespace App\Ai\Extraction;

use App\Ai\AiUsageGuard;
use App\Contracts\InvoiceExtractor;
use App\Enums\DocumentType;
use App\Exceptions\AiExtractionException;
use App\Extraction\RegexInvoiceExtractor;
use App\Models\Document;
use App\Models\DocumentExtraction;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class FieldExtractionService
{
    public function __construct(
        private InvoiceExtractor $extractor,
        private RegexInvoiceExtractor $rules,
        private AiUsageGuard $guard,

    ) {}

    /**
     * @param  bool  $mayRetry  true when the queue can try again later; temporary AI problems are
     *                          then thrown so it does. On the final attempt we fall back to rules.
     */
    public function run(Document $document, DocumentType $hint, bool $mayRetry): DocumentExtraction
    {
        $text = Str::limit(
            (string) $document->extracted_text,
            (int) config('documind.ai.max_input_chars'),
            "\n[…text truncated…]",
        );

        $fallbackReason = null;

        try {
            $usesAi = ! $this->extractor instanceof RegexInvoiceExtractor;

            if ($usesAi && ! $this->guard->attempt($document->business_id)) {
                throw AiExtractionException::dailyLimitReached();
            }

            $result = $this->extractor->extract($text, $hint);
        } catch (AiExtractionException $e) {
            if ($e->retryable && $mayRetry) {
                throw $e;
            }

            Log::warning('AI extraction fell back to rules', [
                'document_id' => $document->id,
                'reason' => $e->getMessage(),
            ]);

            $fallbackReason = $e->getMessage();
            $result = $this->rules->extract($text, $hint);
        }

        $warnings = $result->warnings;

        if ($fallbackReason !== null) {
            array_unshift($warnings, "{$fallbackReason} These fields were found with simple rules instead, so check them carefully.");
        }

        return DocumentExtraction::create([
            'business_id' => $document->business_id,
            'document_id' => $document->id,
            'provider' => $result->usage?->provider ?? 'rules',
            'model' => $result->usage?->model,
            'detected_type' => $result->type,
            'fields' => $result->toFields(),
            'confidence' => $result->confidence,
            'missing_fields' => $result->missingFields(),
            'warnings' => $warnings,
            'summary' => $result->summary,
            'prompt_tokens' => $result->usage?->promptTokens ?? 0,
            'output_tokens' => $result->usage?->outputTokens ?? 0,
            'estimated_cost_usd' => $result->usage?->estimatedCostUsd() ?? 0,
            'latency_ms' => $result->usage?->latencyMs ?? 0,
        ]);
    }
}
