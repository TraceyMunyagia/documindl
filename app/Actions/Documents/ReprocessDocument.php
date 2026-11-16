<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\DocumentStatus;
use App\Jobs\ProcessDocument;
use App\Models\AuditLog;
use App\Models\Document;

final class ReprocessDocument
{
    public function handle(Document $document): void
    {
        $document->forceFill([
            'failure_reason' => null,
            'processing_note' => null,
        ])->save();

        $document->transitionTo(DocumentStatus::Pending); // only allowed from Failed

        AuditLog::record('document.reprocess_requested', $document);

        ProcessDocument::dispatch($document);
    }
}
