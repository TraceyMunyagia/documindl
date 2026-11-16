<?php

declare(strict_types=1);

namespace App\Ai;

use Illuminate\Support\Facades\Cache;

/**
 * A simple daily cap per business, so one busy (or buggy) account can't run up a large bill.
 */
final class AiUsageGuard
{
    /** Counts one call and says whether it's within today's limit. */
    public function attempt(int $businessId): bool
    {
        $limit = (int) config('documind.ai.daily_calls_per_business');

        if ($limit <= 0) {
            return true; // 0 means unlimited
        }

        $key = $this->key($businessId);

        Cache::add($key, 0, now()->endOfDay());

        return Cache::increment($key) <= $limit;
    }

    public function usedToday(int $businessId): int
    {
        return (int) Cache::get($this->key($businessId), 0);
    }

    private function key(int $businessId): string
    {
        return "ai-calls:{$businessId}:".now()->format('Y-m-d');
    }
}
