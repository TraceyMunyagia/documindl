<?php

declare(strict_types=1);

namespace App\Enums;

enum InvoiceStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Paid = 'paid';
    case Overdue = 'overdue';

    public function isOpen(): bool
    {
        return $this !== self::Paid;
    }
}
