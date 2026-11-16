<?php

declare(strict_types=1);

namespace App\Extraction\Text;

use App\Enums\ExtractionMethod;

final readonly class ExtractedText
{
    public function __construct(
        public string $text,
        public ExtractionMethod $method,
    ) {}

    /** Characters that aren't whitespace: a better measure of "how much was read" than strlen. */
    public static function meaningfulLength(string $text): int
    {
        return mb_strlen(preg_replace('/\s+/u', '', $text) ?? '');
    }

    public function hasText(): bool
    {
        return self::meaningfulLength($this->text) >= 10;
    }
}
