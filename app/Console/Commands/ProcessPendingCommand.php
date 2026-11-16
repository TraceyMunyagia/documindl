<?php

namespace App\Console\Commands;

use App\Enums\DocumentStatus;
use App\Jobs\ProcessDocument;
use App\Models\Document;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class ProcessPendingCommand extends Command
{
    protected $signature = 'documind:process-pending
        {--stuck : Also re-queue documents stuck in Processing for 15+ minutes}
        {--business= : Only this business ID}
        {--dry-run : List what would be queued without queueing it}';

    protected $description = 'Queue processing for documents that are waiting (e.g. after the worker was stopped)';

    public function handle(): int
    {
        // No user is signed in here, so the business scope doesn't apply – this sees every business.
        $query = Document::query()
            ->whereNotNull('path')
            ->where(function (Builder $query): void {
                $query->where('status', DocumentStatus::Pending->value);

                if ($this->option('stuck')) {
                    $query->orWhere(function (Builder $query): void {
                        $query->where('status', DocumentStatus::Processing->value)
                            ->where('updated_at', '<', now()->subMinutes(15));
                    });
                }
            })
            ->when($this->option('business'), fn (Builder $query, string $id) => $query->where('business_id', (int) $id));

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->components->info('Nothing to process.');

            return self::SUCCESS;
        }

        $this->components->info(($this->option('dry-run') ? 'Would queue' : 'Queueing')." {$total} document(s).");

        // chunkById keeps memory flat even with thousands of rows.
        $query->chunkById(100, function ($documents): void {
            foreach ($documents as $document) {
                $this->components->twoColumnDetail(
                    "#{$document->id} {$document->original_name}",
                    $document->status->label(),
                );

                if (! $this->option('dry-run')) {
                    ProcessDocument::dispatch($document); // the job handles both Pending and Processing
                }
            }
        });

        return self::SUCCESS;
    }
}
