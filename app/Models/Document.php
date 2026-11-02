<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Concerns\BelongsToBusiness;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $business_id
 * @property int|null $category_id
 * @property int|null $uploaded_by
 * @property string $original_name
 * @property string $disk
 * @property string|null $path
 * @property string|null $mime_type
 * @property int $size_bytes
 * @property DocumentType $type
 * @property DocumentStatus $status
 * @property string|null $extracted_text
 * @property string|null $summary
 * @property string|null $failure_reason
 * @property Carbon|null $processed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $extension
 */
class Document extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'business_id',
        'category_id',
        'uploaded_by',
        'original_name',
        'disk',
        'path',
        'mime_type',
        'size_bytes',
        'type',
        'status',
        'extracted_text',
        'summary',
        'failure_reason',
        'processed_at',
    ];

    protected $attributes = [
        'status' => 'pending',
        'type' => 'other',
        'disk' => 'local',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'status' => DocumentStatus::class,
            'size_bytes' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** @return HasOne<Invoice, $this> */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    /** @return MorphMany<Task, $this> */
    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'taskable');
    }

    /** @return Attribute<lowercase-string, never> */
    protected function extension(): Attribute
    {
        return Attribute::get(fn (): string => strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION)));
    }

    /** Same rule as Week 1, now persisted. */
    public function transitionTo(DocumentStatus $next): void
    {
        if (! $this->status->canTransitionTo($next)) {
            throw InvalidStatusTransitionException::between((string) $this->getKey(), $this->status, $next);
        }

        $this->status = $next;
        $this->save();
    }
}
