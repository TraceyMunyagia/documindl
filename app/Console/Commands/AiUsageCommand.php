<?php

namespace App\Console\Commands;

use App\Ai\AiUsageGuard;
use App\Models\Business;
use App\Models\DocumentExtraction;
use Illuminate\Console\Command;

class AiUsageCommand extends Command
{
    protected $signature = 'documind:ai-usage {--days=30 : How many days back to include}';

    protected $description = 'Show extraction calls, tokens and estimated cost per business';

    public function handle(AiUsageGuard $guard): int
    {
        $days = max(1, (int) $this->option('days'));

        // Console: no signed-in user, so this covers every business.
        $rows = DocumentExtraction::query()
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('business_id, provider, count(*) as runs, sum(prompt_tokens) as tokens_in, sum(output_tokens) as tokens_out, sum(estimated_cost_usd) as cost')
            ->groupBy('business_id', 'provider')
            ->orderBy('business_id')
            ->get();

        if ($rows->isEmpty()) {
            $this->components->info("No extractions in the last {$days} days.");

            return self::SUCCESS;
        }

        $names = Business::query()->whereIn('id', $rows->pluck('business_id'))->pluck('name', 'id');
        $limit = (int) config('documind.ai.daily_calls_per_business');

        $this->table(
            ['Business', 'Provider', 'Runs', 'Tokens in', 'Tokens out', 'Est. cost (USD)', 'AI calls today'],
            $rows->map(fn ($row): array => [
                $names[$row->business_id] ?? "#{$row->business_id}",
                $row->provider,
                number_format($row->runs),
                number_format($row->tokens_in),
                number_format($row->tokens_out),
                '$'.number_format((float) $row->cost, 4),
                $guard->usedToday($row->business_id).($limit > 0 ? " / {$limit}" : ''),
            ]),
        );

        $this->components->twoColumnDetail('Total estimated cost', '$'.number_format((float) $rows->sum('cost'), 4));

        return self::SUCCESS;
    }
}
