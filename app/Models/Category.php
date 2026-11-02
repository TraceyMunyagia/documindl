<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $business_id
 * @property string $name
 * @property string $slug
 * @property string $color
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Category extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'business_id',
        'name',
        'slug',
        'color',
    ];

    protected static function booted(): void
    {
        static::creating(function (Category $category): void {
            $category->slug ??= Str::slug($category->name);
        });
    }

    /** @return HasMany<Document, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
