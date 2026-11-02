<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\InvoiceStatus;
use App\Models\Concerns\BelongsToBusiness;
use App\ValueObjects\Money;
use Carbon\CarbonInterface;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $business_id
 * @property int|null $document_id
 * @property string $supplier_name
 * @property string $invoice_number
 * @property Carbon $issue_date
 * @property Carbon|null $due_date
 * @property string $currency
 * @property int $total_cents
 * @property int|null $tax_cents
 * @property Money|null $total
 * @property Money|null $tax
 * @property InvoiceStatus $status
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Invoice extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    protected $fillable = [
        'business_id',
        'document_id',
        'supplier_name',
        'invoice_number',
        'issue_date',
        'due_date',
        'total',
        'tax',
        'status',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'status' => InvoiceStatus::class,
            'total_cents' => 'integer',
            'tax_cents' => 'integer',
            'total' => MoneyCast::class.':total_cents,currency',
            'tax' => MoneyCast::class.':tax_cents,currency',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return MorphMany<Task, $this> */
    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'taskable');
    }

    /** @param Builder<self> $query */
    public function scopeOutstanding(Builder $query): void
    {
        $query->where('status', '!=', InvoiceStatus::Paid->value);
    }

    /** @param Builder<self> $query */
    public function scopeOverdue(Builder $query, ?CarbonInterface $today = null): void
    {
        $this->scopeOutstanding($query);

        $query->whereNotNull('due_date')
            ->whereDate('due_date', '<', ($today ?? now())->toDateString());
    }

    public function isOverdue(?CarbonInterface $today = null): bool
    {
        return $this->status->isOpen()
            && $this->due_date !== null
            && $this->due_date->lt(($today ?? now())->startOfDay());
    }
}
