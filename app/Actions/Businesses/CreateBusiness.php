<?php

namespace App\Actions\Businesses;

use App\Enums\BusinessRole;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CreateBusiness
{
    private const array DEFAULT_CATEGORIES = [
        'Supplies' => 'amber',
        'Utilities' => 'sky',
        'Rent' => 'rose',
        'Transport' => 'emerald',
        'Professional services' => 'indigo',
    ];

    public function handle(User $owner, string $name, string $currency = 'KES'): Business
    {
        return DB::transaction(function () use ($owner, $name, $currency): Business {
            $business = Business::create([
                'owner_id' => $owner->id,
                'name' => trim($name),
                'currency' => strtoupper($currency),
            ]);

            $business->members()->attach($owner->id, ['role' => BusinessRole::Owner->value]);
            $owner->switchBusiness($business);

            foreach (self::DEFAULT_CATEGORIES as $categoryName => $color) {
                $business->categories()->create(['name' => $categoryName, 'color' => $color]);
            }

            AuditLog::record('business.created', $business);

            return $business;
        });
    }
}
