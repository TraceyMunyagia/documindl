<?php

namespace App\Jobs;

use App\Ai\Extraction\FieldExtractionService;
use App\Contracts\DocumentClassifier;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Exceptions\TextExtractionFailedException;
use App\Extraction\Text\DocumentTextExtractor;
use App\Models\AuditLog;
use App\Models\Document;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

class ProcessDocument implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 240;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Document $document) {}

    /** Waits before attempts 2 and 3. Long enough for an AI rate limit to clear. */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function uniqueId(): string
    {
        return (string) $this->document->getKey();
    }

    public function handle(
        DocumentTextExtractor $textExtractor,
        DocumentClassifier $classifier,
        FieldExtractionService $fieldExtraction,
    ): void {
        $document = $this->document->fresh();

        if ($document === null) {
            return;
        }

        if ($document->status === DocumentStatus::Pending) {
            $document->transitionTo(DocumentStatus::Processing);
        } elseif ($document->status !== DocumentStatus::Processing) {
            return;
        }

        // Stage 1: text. Skipped on a retry if an earlier attempt already saved it.
        if ($document->extraction_method === null) {
            try {
                $text = $textExtractor->extract($document);
            } catch (TextExtractionFailedException $e) {
                $this->markFailed($document, $e->getMessage());

                return;
            }

            $document->fill([
                'extracted_text' => $text->hasText() ? $text->text : null,
                'extraction_method' => $text->method,
                'processing_note' => $text->hasText()
                    ? null
                    : 'No readable text was found. Check that the scan is clear, or enter the details by hand.',
            ])->save();

            AuditLog::record('document.text_extracted', $document, [
                'method' => $text->method->value,
                'characters' => mb_strlen($text->text),
            ]);
        }

        if ($document->extracted_text === null) {
            $document->forceFill(['processed_at' => now()])->save();
            $document->transitionTo(DocumentStatus::ReviewRequired);

            return;
        }

        // Stage 2: structured fields (Gemini, or rules as a fallback).
        $chosenByHand = $document->type !== DocumentType::Other;
        $hint = $chosenByHand ? $document->type : $classifier->classify($document->extracted_text);

        $extraction = $fieldExtraction->run($document, $hint, mayRetry: $this->mayRetry());

        $type = $chosenByHand ? $document->type : $extraction->detected_type;

        $document->fill([
            'type' => $type,
            'failure_reason' => null,
            'processed_at' => now(),
        ])->save();

        // Financial documents wait for a person to verify the fields (Week 8).
        $document->transitionTo($type->isFinancial() ? DocumentStatus::ReviewRequired : DocumentStatus::Completed);

        AuditLog::record('document.processed', $document, [
            'provider' => $extraction->provider,
            'type' => $type->value,
            'missing' => $extraction->missing_fields,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $document = $this->document->fresh();

        if ($document !== null) {
            $this->markFailed(
                $document,
                'Processing stopped unexpectedly after several attempts. Try again, and contact support if it keeps happening.',
            );
        }
    }

    /** Only a real queued attempt with attempts left may be retried; dispatchSync() never can. */
    private function mayRetry(): bool
    {
        return $this->job !== null && $this->attempts() < $this->tries;
    }

    private function markFailed(Document $document, string $reason): void
    {
        $document->forceFill([
            'failure_reason' => Str::limit($reason, 500),
            'processed_at' => now(),
        ]);

        if ($document->status->canTransitionTo(DocumentStatus::Failed)) {
            $document->transitionTo(DocumentStatus::Failed);
        } else {
            $document->forceFill(['status' => DocumentStatus::Failed])->save();
        }

        AuditLog::record('document.processing_failed', $document, ['reason' => $document->failure_reason]);
    }
}
