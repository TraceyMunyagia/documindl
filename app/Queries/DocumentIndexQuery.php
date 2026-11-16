<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Document;
use App\Support\DocumentFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

final class DocumentIndexQuery
{
    public function build(DocumentFilters $filters): Builder
    {
        $timezone = config('documind.display_timezone');

        return Document::query()
            ->with(['category', 'uploader'])
            ->search($filters->search)
            ->when($filters->type, fn (Builder $query, DocumentType $type) => $query->where('type', $type->value))
            ->when($filters->status, fn (Builder $query, DocumentStatus $status) => $query->where('status', $status->value))
            ->when($filters->category === 'none', fn (Builder $query) => $query->whereNull('category_id'))
            ->when(
                $filters->category !== null && $filters->category !== 'none',
                fn (Builder $query) => $query->where('category_id', (int) $filters->category),
            )
            ->when($filters->uploadedBy, fn (Builder $query, int $userId) => $query->where('uploaded_by', $userId))
            // Dates are picked in Nairobi time but stored in UTC, so convert the day's boundaries.
            ->when($filters->from, fn (Builder $query, string $from) => $query->where(
                'documents.created_at', '>=', Carbon::parse($from, $timezone)->startOfDay()->utc(),
            ))
            ->when($filters->to, fn (Builder $query, string $to) => $query->where(
                'documents.created_at', '<=', Carbon::parse($to, $timezone)->endOfDay()->utc(),
            ))
            ->orderBy("documents.{$filters->sort}", $filters->direction)
            ->orderBy('documents.id', $filters->direction); // stable order when values tie
    }
}
