<?php

declare(strict_types=1);

namespace App\Ai\Extraction;

use App\Ai\Gemini\GeminiClient;
use App\Contracts\InvoiceExtractor;
use App\Enums\DocumentType;
use App\Extraction\ExtractionResult;

/**
 * The same interface the Week 1 regex extractor implements – nothing that
 * calls an InvoiceExtractor needs to know which one it got.
 */
final class GeminiInvoiceExtractor implements InvoiceExtractor
{
    public function __construct(
        private GeminiClient $client,
        private ExtractionPayload $payload,
    ) {}

    public function extract(string $text, DocumentType $type): ExtractionResult
    {
        $response = $this->client->generateJson(
            ExtractionPrompt::system(),
            ExtractionPrompt::user($text, $type),
            ExtractionPrompt::schema(),
        );

        return $this->payload->toResult($response->json, $text, $response->usage);
    }
}
