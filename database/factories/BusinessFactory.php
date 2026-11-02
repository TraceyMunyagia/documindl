<?php

namespace Database\Factories;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Business>
 */
class BusinessFactory extends Factory
{
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'name' => fake()->company(),
            'currency' => 'KES',
            'timezone' => 'Africa/Nairobi',
        ];
    }

    /** Every business gets its owner as a member, and the owner gets a current business. */
    public function configure(): static
    {
        return $this->afterCreating(function (Business $business): void {
            $business->members()->syncWithoutDetaching([
                $business->owner_id => ['role' => BusinessRole::Owner->value],
            ]);

            $owner = $business->owner;

            if ($owner->current_business_id === null) {
                $owner->forceFill(['current_business_id' => $business->getKey()])->save();
            }
        });
    }
}
