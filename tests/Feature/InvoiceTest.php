<?php

use App\Models\Business;
use App\Models\Invoice;
use App\ValueObjects\Money;

test('invoice totals are stored as cents and read back as Money', function () {
    $invoice = Invoice::factory()->for(Business::factory())->create([
        'total' => Money::fromDecimal('13,660.16', 'KES'),
        'tax' => Money::fromDecimal('1,884.16', 'KES'),
    ]);

    $fresh = $invoice->fresh();

    expect($fresh->total_cents)->toBe(1366016)
        ->and($fresh->currency)->toBe('KES')
        ->and($fresh->total)->toBeInstanceOf(Money::class)
        ->and($fresh->total->format())->toBe('KES 13,660.16')
        ->and($fresh->tax->format())->toBe('KES 1,884.16');
});

test('the overdue scope ignores paid and not-yet-due invoices', function () {
    $business = Business::factory()->create();

    Invoice::factory()->for($business)->overdue()->create();
    Invoice::factory()->for($business)->create();        // due in the future
    Invoice::factory()->for($business)->paid()->create(); // past due but paid

    expect(Invoice::overdue()->count())->toBe(1)
        ->and(Invoice::outstanding()->count())->toBe(2);
});
