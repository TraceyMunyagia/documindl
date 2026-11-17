<?php

declare(strict_types=1);

namespace App\Review;

final class ReviewCorrections
{
    public const array COMPARED = [
        'document_type',
        'supplier_name',
        'document_number',
        'issue_date',
        'due_date',
        'currency',
        'total_cents',
        'tax_cents',
    ];

    /**
     * Fields whose meaning changed. "SAVANNAH LTD" → "Savannah Ltd" isn't a correction;
     * "Savannah Ltd" → "Savanna Ltd" is.
     *
     * @return array<string, array{ai: mixed, final: mixed}>
     */
    public static function between(array $ai, array $final): array
    {
        $changes = [];

        foreach (self::COMPARED as $field) {
            if (self::normalise($ai[$field] ?? null) !== self::normalise($final[$field] ?? null)) {
                $changes[$field] = ['ai' => $ai[$field] ?? null, 'final' => $final[$field] ?? null];
            }
        }

        return $changes;
    }

    private static function normalise(mixed $value): mixed
    {
        if (is_string($value)) {
            $value = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value) ?? ''));

            return $value === '' ? null : $value;
        }

        if (is_float($value) && floor($value) === $value) {
            return (int) $value;
        }

        return $value;
    }
}
