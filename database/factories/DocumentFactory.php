<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Business;
use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    public function definition(): array
    {
        $type = fake()->randomElement([DocumentType::Invoice, DocumentType::Receipt]);

        return [
            'business_id' => Business::factory(),
            'original_name' => $type->value.'-'.fake()->numerify('####').'.pdf',
            'disk' => 'local',
            'path' => null,
            'mime_type' => 'application/pdf',
            'size_bytes' => fake()->numberBetween(40_000, 2_000_000),
            'type' => $type,
            'status' => DocumentStatus::Completed,
            'summary' => fake()->sentence(12),
            'processed_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => DocumentStatus::Pending,
            'type' => DocumentType::Other,
            'summary' => null,
            'processed_at' => null,
        ]);
    }

    public function reviewRequired(): static
    {
        return $this->state(fn (): array => [
            'status' => DocumentStatus::ReviewRequired,
            'type' => DocumentType::Invoice,
        ]);
    }
}
