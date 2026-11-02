<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'name' => ucfirst(fake()->unique()->word().' '.fake()->word()),
            'color' => fake()->randomElement(['indigo', 'emerald', 'amber', 'rose', 'sky', 'zinc']),
        ];
    }
}
