<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * $retryable tells the queue whether trying again later could succeed.
 * Messages are stored as warnings, so they're written for users.
 */
final class AiExtractionException extends DocuMindException
{
    public bool $retryable = false;

    private static function make(string $message, bool $retryable): self
    {
        $exception = new self($message);
        $exception->retryable = $retryable;

        return $exception;
    }

    public static function notConfigured(): self
    {
        return self::make('AI extraction is not configured.', false);
    }

    public static function unreachable(): self
    {
        return self::make('The AI service could not be reached.', true);
    }

    public static function rateLimited(): self
    {
        return self::make('The AI service is busy right now.', true);
    }

    public static function serviceError(int $status): self
    {
        return self::make("The AI service had a temporary problem ({$status}).", true);
    }

    public static function badKey(): self
    {
        return self::make('The AI service rejected our credentials.', false);
    }

    public static function modelNotFound(string $model): self
    {
        return self::make("The AI model \"{$model}\" isn't available.", false);
    }

    public static function rejected(string $reason): self
    {
        return self::make('The AI service rejected the request: '.mb_substr($reason, 0, 200), false);
    }

    public static function blocked(string $reason): self
    {
        return self::make("The AI service declined to read this document ({$reason}).", false);
    }

    public static function truncated(): self
    {
        return self::make('The AI response was cut off before it finished.', false);
    }

    public static function invalidResponse(string $detail): self
    {
        return self::make("The AI response wasn't in the expected format ({$detail}).", true);
    }

    public static function dailyLimitReached(): self
    {
        return self::make("Today's AI extraction limit for this business has been reached.", false);
    }
}
