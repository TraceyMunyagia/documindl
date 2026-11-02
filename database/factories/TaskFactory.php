<?php

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\Business;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'title' => fake()->randomElement([
                'Confirm delivery before paying',
                'Request a corrected invoice',
                'File receipt under the right category',
                'Check VAT amount with the accountant',
            ]),
            'description' => fake()->optional()->sentence(),
            'status' => TaskStatus::Open,
            'due_date' => now()->addDays(fake()->numberBetween(1, 14)),
        ];
    }

    public function done(): static
    {
        return $this->state(fn (): array => [
            'status' => TaskStatus::Done,
            'completed_at' => now(),
        ]);
    }
}
