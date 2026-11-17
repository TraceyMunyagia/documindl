<?php

declare(strict_types=1);

namespace App\Review;

use App\Models\DocumentExtraction;

final class ReviewStats
{
    /**
     * How the AI performed, judged by what reviewers changed (current business, via the scope).
     *
     * @return array{reviewed: int, unchanged_percent: int|null, median_seconds: int|null, field_rates: array<string, int>}
     */
    public function lastDays(int $days = 30, string $provider = 'gemini'): array
    {
        $rows = DocumentExtraction::query()
            ->whereNotNull('verified_at')
            ->where('verified_at', '>=', now()->subDays($days))
            ->where('provider', $provider)
            ->get(['corrections', 'review_seconds']);

        $reviewed = $rows->count();

        if ($reviewed === 0) {
            return ['reviewed' => 0, 'unchanged_percent' => null, 'median_seconds' => null, 'field_rates' => []];
        }

        $rates = [];

        foreach (ReviewCorrections::COMPARED as $field) {
            $corrected = $rows->filter(fn (DocumentExtraction $row): bool => array_key_exists($field, $row->corrections ?? []))->count();
            $rates[$field] = (int) round($corrected / $reviewed * 100);
        }

        arsort($rates);

        $median = $rows->pluck('review_seconds')->filter()->median();

        return [
            'reviewed' => $reviewed,
            'unchanged_percent' => (int) round($rows->filter(fn (DocumentExtraction $row): bool => empty($row->corrections))->count() / $reviewed * 100),
            'median_seconds' => $median === null ? null : (int) $median,
            'field_rates' => array_filter($rates), // only fields that were corrected at least once
        ];
    }
}
