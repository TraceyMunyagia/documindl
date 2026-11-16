<?php

declare(strict_types=1);

namespace App\Ai;

final readonly class ExtractionUsage
{
    public function __construct(
        public string $provider,
        public ?string $model = null,
        public int $promptTokens = 0,
        public int $outputTokens = 0,
        public int $latencyMs = 0,
    ) {}

    /** An estimate from the prices in config – your provider's invoice is the source of truth. */
    public function estimatedCostUsd(): float
    {
        $prices = config("documind.ai.pricing.{$this->provider}", []);

        return round(
            ($this->promptTokens * (float) ($prices['input_per_million'] ?? 0)
                + $this->outputTokens * (float) ($prices['output_per_million'] ?? 0)) / 1_000_000,
            6,
        );
    }
}
