<?php

namespace App\Jobs;

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

    /** Total attempts for unexpected errors (timeouts, crashes). */
    public int $tries = 3;

    /** Seconds before this job is killed. Must stay below DB_QUEUE_RETRY_AFTER. */
    public int $timeout = 240;

    /** If the document is deleted before the job runs, quietly drop the job. */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public Document $document) {}

    /** Wait 15s before the 2nd attempt, 60s before the 3rd. */
    public function backoff(): array
    {
        return [15, 60];
    }

    /** Only one queued job per document at a time. */
    public function uniqueId(): string
    {
        return (string) $this->document->getKey();
    }

    public function handle(DocumentTextExtractor $extractor, DocumentClassifier $classifier): void
    {
        $document = $this->document->fresh();

        if ($document === null) {
            return;
        }

        // Idempotent: a retry finds the document already "Processing" and carries on;
        // a duplicate dispatch finds it finished and does nothing.
        if ($document->status === DocumentStatus::Pending) {
            $document->transitionTo(DocumentStatus::Processing);
        } elseif ($document->status !== DocumentStatus::Processing) {
            return;
        }

        try {
            $result = $extractor->extract($document);
        } catch (TextExtractionFailedException $e) {
            // Retrying won't help (missing tool, damaged file), so fail now with a clear reason.
            $this->markFailed($document, $e->getMessage());

            return;
        }

        // Respect a type someone already chose by hand.
        $type = $document->type === DocumentType::Other && $result->hasText()
            ? $classifier->classify($result->text)
            : $document->type;

        $document->fill([
            'extracted_text' => $result->hasText() ? $result->text : null,
            'extraction_method' => $result->method,
            'type' => $type,
            'failure_reason' => null,
            'processing_note' => $result->hasText()
                ? null
                : 'No readable text was found. Check that the scan is clear, or enter the details by hand.',
            'processed_at' => now(),
        ])->save();

        // Financial documents wait for field extraction and human review (Weeks 7–8).
        $document->transitionTo(match (true) {
            ! $result->hasText() => DocumentStatus::ReviewRequired,
            $type->isFinancial() => DocumentStatus::ReviewRequired,
            default => DocumentStatus::Completed,
        });

        AuditLog::record('document.processed', $document, [
            'method' => $result->method->value,
            'type' => $type->value,
            'characters' => mb_strlen($result->text),
        ]);
    }

    /** Called by the queue after the final attempt throws. */
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

    private function markFailed(Document $document, string $reason): void
    {
        $document->forceFill([
            'failure_reason' => Str::limit($reason, 500),
            'processed_at' => now(),
        ]);

        if ($document->status->canTransitionTo(DocumentStatus::Failed)) {
            $document->transitionTo(DocumentStatus::Failed);
        } else {
            // e.g. still "Pending" because it crashed before starting: record the failure anyway.
            $document->forceFill(['status' => DocumentStatus::Failed])->save();
        }

        AuditLog::record('document.processing_failed', $document, ['reason' => $document->failure_reason]);
    }
}
