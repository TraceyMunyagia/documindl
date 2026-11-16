<?php

declare(strict_types=1);

namespace App\Ai\Gemini;

use App\Ai\ExtractionUsage;

final readonly class GeminiResponse
{
    /** @param array<string, mixed> $json */
    public function __construct(
        public array $json,
        public ExtractionUsage $usage,
    ) {}
}
