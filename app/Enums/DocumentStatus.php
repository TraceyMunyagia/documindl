<?php

declare(strict_types=1);

namespace App\Enums;

enum DocumentStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case ReviewRequired = 'review_required';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::ReviewRequired => 'Review Required',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'zinc',
            self::Processing => 'sky',
            self::ReviewRequired => 'amber',
            self::Completed => 'emerald',
            self::Failed => 'rose',
        };
    }

    /** @return list<self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Pending => [self::Processing],
            self::Processing => [self::ReviewRequired, self::Completed, self::Failed],
            self::ReviewRequired => [self::Completed],
            self::Completed => [],
            self::Failed => [self::Pending],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }
}
