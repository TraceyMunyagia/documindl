<?php

use App\Exceptions\InvalidMoneyException;
use App\ValueObjects\Money;

it('parses formatted decimal amounts into cents', function () {
    $money = Money::fromDecimal('13,660.16', 'kes');

    expect($money->amountInCents)->toBe(1366016)
        ->and($money->currency)->toBe('KES')
        ->and($money->format())->toBe('KES 13,660.16');
});

it('adds amounts in the same currency', function () {
    $sum = Money::fromDecimal('100.50', 'KES')->add(Money::fromDecimal('0.50', 'KES'));

    expect($sum->amountInCents)->toBe(10100);
});

it('refuses to mix currencies', function () {
    Money::fromDecimal(10, 'KES')->add(Money::fromDecimal(10, 'USD'));
})->throws(InvalidMoneyException::class);

it('rejects invalid input', function (string $amount, string $currency) {
    Money::fromDecimal($amount, $currency);
})->throws(InvalidMoneyException::class)->with([
    'not a number' => ['abc', 'KES'],
    'negative' => ['-5', 'KES'],
    'bad currency' => ['10', 'KE'],
]);
