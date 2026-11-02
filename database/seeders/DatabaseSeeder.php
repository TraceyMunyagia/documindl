<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * A verified demo account you can always log in with.
     * The factory's default password is "password".
     * Week 3 adds a demo business and team members here.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Demo Owner',
            'email' => 'owner@documind.test',
        ]);
    }
}
