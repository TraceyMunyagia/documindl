<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Business;
use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /** Fictional suppliers so the demo data feels local. */
    private const array SUPPLIERS = [
        'Savannah Office Supplies Ltd',
        'Rift Valley Flour Mills',
        'Lakeview Dairy Co-operative',
        'Nyali Packaging Ltd',
        'Athi River Gas Supplies',
        'Kilimanjaro Transport Ltd',
        'Upper Hill Cleaning Services',
        'Mlimani Electricals',
    ];

    public function definition(): array
    {
        $issued = CarbonImmutable::instance(fake()->dateTimeBetween('-25 days', 'now'))->startOfDay();
        $totalCents = fake()->numberBetween(1_500, 250_000) * 100;

        return [
            'business_id' => Business::factory(),
            'document_id' => null,
            'supplier_name' => fake()->randomElement(self::SUPPLIERS),
            'invoice_number' => 'INV-'.fake()->unique()->numerify('2026-####'),
            'issue_date' => $issued,
            'due_date' => $issued->addDays(30),
            'currency' => 'KES',
            'total_cents' => $totalCents,
            'tax_cents' => intdiv($totalCents * 16, 116), // 16% VAT included in the total
            'status' => InvoiceStatus::Pending,
        ];
    }

    public function overdue(): static
    {
        return $this->state(function (): array {
            $issued = CarbonImmutable::now()->subDays(fake()->numberBetween(40, 60))->startOfDay();

            return [
                'issue_date' => $issued,
                'due_date' => $issued->addDays(30),
                'status' => InvoiceStatus::Pending,
            ];
        });
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => InvoiceStatus::Approved,
            'approved_at' => now()->subDays(2),
        ]);
    }

    public function paid(): static
    {
        return $this->state(function (): array {
            $issued = CarbonImmutable::now()->subDays(fake()->numberBetween(45, 90))->startOfDay();

            return [
                'issue_date' => $issued,
                'due_date' => $issued->addDays(30),
                'status' => InvoiceStatus::Paid,
                'approved_at' => $issued->addDays(3),
                'paid_at' => $issued->addDays(20),
            ];
        });
    }
}
