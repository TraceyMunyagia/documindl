<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Document;
use App\Models\User;

final class MoveDocumentsToCategory
{
    /**
     * Moves the documents the user may edit; skips the rest.
     *
     * @param  list<int>  $documentIds
     * @return int how many were moved
     */
    public function handle(User $user, array $documentIds, ?Category $category): int
    {
        $moved = [];

        // Scoped query: IDs from another business simply aren't found.
        foreach (Document::query()->whereKey($documentIds)->get() as $document) {
            if ($user->cannot('update', $document)) {
                continue;
            }

            $document->update(['category_id' => $category?->getKey()]);
            $moved[] = $document->getKey();
        }

        if ($moved !== []) {
            AuditLog::record('documents.moved', $category, [
                'document_ids' => $moved,
                'category_id' => $category?->getKey(),
            ]);
        }

        return count($moved);
    }
}
