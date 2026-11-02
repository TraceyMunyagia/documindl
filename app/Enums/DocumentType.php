<?php

declare(strict_types=1);

namespace App\Enums;

enum DocumentType: string
{
    case Invoice = 'invoice';
    case Receipt = 'receipt';
    case Contract = 'contract';
    case Other = 'other';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function isFinancial(): bool
    {
        return in_array($this, [self::Invoice, self::Receipt], true);
    }
}
