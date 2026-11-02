<?php

declare(strict_types=1);

namespace App\ValueObjects;

use App\Exceptions\InvalidMoneyException;

/**
 * Money is stored as integer cents to avoid floating-point rounding errors.
 */
final readonly class Money
{
    public function __construct(
        public int $amountInCents,
        public string $currency,
    ) {
        if ($amountInCents < 0) {
            throw InvalidMoneyException::negative();
        }

        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw InvalidMoneyException::invalidCurrency($currency);
        }
    }

    public static function fromDecimal(string|int|float $amount, string $currency): self
    {
        $clean = str_replace([',', ' '], '', (string) $amount);

        if (! is_numeric($clean)) {
            throw InvalidMoneyException::notNumeric((string) $amount);
        }

        return new self((int) round((float) $clean * 100), strtoupper($currency));
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amountInCents + $other->amountInCents, $this->currency);
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency
            && $this->amountInCents === $other->amountInCents;
    }

    public function format(): string
    {
        return sprintf('%s %s', $this->currency, number_format($this->amountInCents / 100, 2));
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw InvalidMoneyException::currencyMismatch($this->currency, $other->currency);
        }
    }
}
