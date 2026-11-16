<?php

declare(strict_types=1);

namespace App\Ai\Gemini;

use App\Ai\ExtractionUsage;
use App\Exceptions\AiExtractionException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

final class GeminiClient
{
    /**
     * Sends one prompt and returns the decoded JSON the model produced.
     *
     * @param  array<string, mixed>  $schema  JSON Schema the response must follow
     */
    public function generateJson(string $systemInstruction, string $prompt, array $schema): GeminiResponse
    {
        $apiKey = (string) config('services.gemini.key');
        $model = (string) config('services.gemini.model');

        if ($apiKey === '') {
            throw AiExtractionException::notConfigured();
        }

        $generationConfig = array_filter([
            'responseMimeType' => 'application/json',
            'responseJsonSchema' => $schema,
            'maxOutputTokens' => (int) config('documind.ai.max_output_tokens'),
            'thinkingConfig' => config('documind.ai.thinking_level')
                ? ['thinkingLevel' => config('documind.ai.thinking_level')]
                : null,
        ], fn (mixed $value): bool => $value !== null);

        $started = hrtime(true);

        try {
            $response = Http::baseUrl((string) config('services.gemini.base_url'))
                ->withHeaders(['x-goog-api-key' => $apiKey]) // a header, never the URL: URLs end up in logs
                ->acceptJson()
                ->asJson()
                ->connectTimeout(10)
                ->timeout((int) config('documind.ai.request_timeout'))
                // Retry brief network blips and 5xx errors here (2s, then 4s).
                // 429 "slow down" is left to the queue's longer backoff.
                ->retry(
                    2,
                    fn (int $attempt): int => $attempt * 2000,
                    fn (Throwable $e): bool => $e instanceof ConnectionException
                        || ($e instanceof RequestException && $e->response->serverError()),
                    throw: false,
                )
                ->post("models/{$model}:generateContent", [
                    'systemInstruction' => ['parts' => [['text' => $systemInstruction]]],
                    'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                    'generationConfig' => $generationConfig,
                ]);
        } catch (ConnectionException) {
            throw AiExtractionException::unreachable();
        }

        $latencyMs = (int) ((hrtime(true) - $started) / 1_000_000);

        if ($response->failed()) {
            throw match (true) {
                $response->status() === 429 => AiExtractionException::rateLimited(),
                in_array($response->status(), [401, 403], true) => AiExtractionException::badKey(),
                $response->status() === 404 => AiExtractionException::modelNotFound($model),
                $response->serverError() => AiExtractionException::serviceError($response->status()),
                default => AiExtractionException::rejected((string) $response->json('error.message', 'unknown error')),
            };
        }

        if ($blockReason = $response->json('promptFeedback.blockReason')) {
            throw AiExtractionException::blocked((string) $blockReason);
        }

        $candidate = $response->json('candidates.0');

        if (! is_array($candidate)) {
            throw AiExtractionException::invalidResponse('no answer returned');
        }

        if (($candidate['finishReason'] ?? null) === 'MAX_TOKENS') {
            throw AiExtractionException::truncated();
        }

        // Join the answer's text parts, skipping any reasoning ("thought") parts.
        $text = collect($candidate['content']['parts'] ?? [])
            ->reject(fn (array $part): bool => (bool) ($part['thought'] ?? false))
            ->pluck('text')
            ->filter()
            ->implode('');

        $json = json_decode($text, true);

        if (! is_array($json)) {
            throw AiExtractionException::invalidResponse('not valid JSON');
        }

        return new GeminiResponse($json, new ExtractionUsage(
            provider: 'gemini',
            model: (string) $response->json('modelVersion', $model),
            promptTokens: (int) $response->json('usageMetadata.promptTokenCount', 0),
            // Reasoning tokens are billed as output.
            outputTokens: (int) $response->json('usageMetadata.candidatesTokenCount', 0)
                + (int) $response->json('usageMetadata.thoughtsTokenCount', 0),
            latencyMs: $latencyMs,
        ));
    }
}
