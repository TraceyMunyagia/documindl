<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\User;

final class DeleteDocuments
{
    /**
     * Soft-deletes the documents the user may delete; skips the rest.
     *
     * @param  list<int>  $documentIds
     */
    public function handle(User $user, array $documentIds): int
    {
        $deleted = 0;

        foreach (Document::query()->whereKey($documentIds)->get() as $document) {
            if ($user->cannot('delete', $document)) {
                continue;
            }

            $document->delete();
            AuditLog::record('document.deleted', $document, ['name' => $document->original_name, 'bulk' => true]);
            $deleted++;
        }

        return $deleted;
    }
}
