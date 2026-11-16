<?php

use App\Ai\Gemini\GeminiClient;
use App\Ai\Gemini\GeminiResponse;
use App\Exceptions\AiExtractionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(function () {
    Sleep::fake(); // retries wait 2s and 4s in real life; not in tests
});

function callGemini(): GeminiResponse
{
    return app(GeminiClient::class)->generateJson('system', 'prompt', ['type' => 'object']);
}

test('it sends the key as a header and asks for JSON matching the schema', function () {
    Http::fake(['generativelanguage.googleapis.com/*' => geminiReply(['ok' => true], 1000, 50)]);

    $response = callGemini();

    expect($response->json)->toBe(['ok' => true])
        ->and($response->usage->promptTokens)->toBe(1000)
        ->and($response->usage->outputTokens)->toBe(50)
        ->and($response->usage->model)->toBe('gemini-test-model');

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('x-goog-api-key', 'test-key')
        && str_contains($request->url(), 'models/gemini-test-model:generateContent')
        && ! str_contains($request->url(), 'test-key')
        && $request['generationConfig']['responseMimeType'] === 'application/json'
        && $request['generationConfig']['responseJsonSchema'] === ['type' => 'object']);
});

test('thinking tokens are counted as output', function () {
    Http::fake(['*' => Http::response([
        'candidates' => [['content' => ['parts' => [
            ['text' => 'reasoning…', 'thought' => true],
            ['text' => '{"ok":true}'],
        ]], 'finishReason' => 'STOP']],
        'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 5, 'thoughtsTokenCount' => 20],
    ])]);

    $response = callGemini();

    expect($response->json)->toBe(['ok' => true])
        ->and($response->usage->outputTokens)->toBe(25);
});

test('server errors are retried before succeeding', function () {
    Http::fakeSequence()
        ->push(['error' => ['message' => 'unavailable']], 503)
        ->push(geminiReply(['ok' => true])->wait()->getBody()->getContents(), 200);

    expect(callGemini()->json)->toBe(['ok' => true]);

    Http::assertSentCount(2);
});

test('errors become exceptions that know whether to retry', function (int $status, string $message, bool $retryable) {
    Http::fake(['*' => Http::response(['error' => ['message' => 'nope']], $status)]);

    try {
        callGemini();
        $this->fail('Expected an exception');
    } catch (AiExtractionException $e) {
        expect($e->getMessage())->toContain($message)
            ->and($e->retryable)->toBe($retryable);
    }
})->with([
    'rate limited' => [429, 'busy', true],
    'bad key' => [403, 'credentials', false],
    'unknown model' => [404, "isn't available", false],
    'bad request' => [400, 'rejected the request', false],
    'still down after retries' => [503, 'temporary problem', true],
]);

test('a missing key fails before any request is made', function () {
    config(['services.gemini.key' => '']);
    Http::fake();

    expect(fn () => callGemini())->toThrow(AiExtractionException::class, 'not configured');

    Http::assertNothingSent();
});

test('answers that are not JSON, or were cut off, are rejected', function (array $candidate, string $message) {
    Http::fake(['*' => Http::response(['candidates' => [$candidate]])]);

    expect(fn () => callGemini())->toThrow(AiExtractionException::class, $message);
})->with([
    'not json' => [['content' => ['parts' => [['text' => 'Sure! Here you go']]], 'finishReason' => 'STOP'], 'not valid JSON'],
    'cut off' => [['content' => ['parts' => [['text' => '{"ok":']]], 'finishReason' => 'MAX_TOKENS'], 'cut off'],
]);
