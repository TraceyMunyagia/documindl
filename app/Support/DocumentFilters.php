<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;

/**
 * Everything in the URL is untrusted. This turns raw strings into
 * known-good values; anything invalid is silently dropped.
 */
final readonly class DocumentFilters
{
    public const array SORTABLE = ['created_at', 'original_name', 'size_bytes', 'status'];

    public function __construct(
        public string $search = '',
        public ?DocumentType $type = null,
        public ?DocumentStatus $status = null,
        public ?string $category = null, // a category ID, or "none" for uncategorised
        public ?int $uploadedBy = null,
        public ?string $from = null,     // Y-m-d, in the display timezone
        public ?string $to = null,
        public string $sort = 'created_at',
        public string $direction = 'desc',
    ) {}

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        $category = (string) ($input['category'] ?? '');
        $uploader = (string) ($input['uploader'] ?? '');
        $sort = (string) ($input['sort'] ?? '');

        return new self(
            search: mb_substr(trim((string) ($input['search'] ?? '')), 0, 100),
            type: DocumentType::tryFrom((string) ($input['type'] ?? '')),
            status: DocumentStatus::tryFrom((string) ($input['status'] ?? '')),
            category: $category === 'none' || ctype_digit($category) ? $category : null,
            uploadedBy: ctype_digit($uploader) ? (int) $uploader : null,
            from: self::date($input['from'] ?? null),
            to: self::date($input['to'] ?? null),
            sort: in_array($sort, self::SORTABLE, true) ? $sort : 'created_at',
            direction: ($input['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc',
        );
    }

    private static function date(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));

        return checkdate($month, $day, $year) ? $value : null;
    }
}
