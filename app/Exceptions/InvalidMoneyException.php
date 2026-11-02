<?php

declare(strict_types=1);

namespace App\Exceptions;

final class InvalidMoneyException extends DocuMindException
{
    public static function negative(): self
    {
        return new self('Money cannot be negative.');
    }

    public static function invalidCurrency(string $currency): self
    {
        return new self("Invalid currency code: {$currency}");
    }

    public static function notNumeric(string $amount): self
    {
        return new self("Not a valid amount: {$amount}");
    }

    public static function currencyMismatch(string $a, string $b): self
    {
        return new self("Cannot combine {$a} with {$b}.");
    }
}
