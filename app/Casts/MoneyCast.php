<?php

declare(strict_types=1);

namespace App\Casts;

use App\ValueObjects\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Maps a virtual Money attribute onto two real columns.
 * Usage: 'total' => MoneyCast::class . ':total_cents,currency'
 *
 * @implements CastsAttributes<Money|null, mixed>
 */
final class MoneyCast implements CastsAttributes
{
    public function __construct(
        private string $amountColumn,
        private string $currencyColumn = 'currency',
    ) {}

    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        $amount = $attributes[$this->amountColumn] ?? null;

        if ($amount === null) {
            return null;
        }

        $currency = $attributes[$this->currencyColumn] ?? config('documind.default_currency');

        return new Money((int) $amount, (string) $currency);
    }

    /** @return array<string, mixed> */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$this->amountColumn => null];
        }

        if (! $value instanceof Money) {
            throw new InvalidArgumentException("The {$key} attribute must be a Money instance.");
        }

        return [
            $this->amountColumn => $value->amountInCents,
            $this->currencyColumn => $value->currency,
        ];
    }
}
