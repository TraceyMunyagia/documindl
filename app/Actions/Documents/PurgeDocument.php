<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Models\AuditLog;
use App\Models\Document;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class PurgeDocument
{
    public function handle(Document $document): void
    {
        $disk = $document->disk;
        $path = $document->path;

        DB::transaction(function () use ($document): void {
            AuditLog::record('document.purged', $document, [
                'name' => $document->original_name,
                'checksum' => $document->checksum,
            ]);

            $document->tasks()->delete();
            $document->forceDelete(); // invoices keep their data; document_id becomes null
        });

        // Only remove the file once the database change is committed.
        if ($path !== null) {
            Storage::disk($disk)->delete($path);
        }
    }
}
