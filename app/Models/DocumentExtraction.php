<?php

namespace App\Models;

use App\Enums\DocumentType;
use App\Models\Concerns\BelongsToBusiness;
use App\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentExtraction extends Model
{
    use BelongsToBusiness;

    protected $fillable = [
        'business_id',
        'document_id',
        'provider',
        'model',
        'detected_type',
        'fields',
        'confidence',
        'missing_fields',
        'warnings',
        'summary',
        'prompt_tokens',
        'output_tokens',
        'estimated_cost_usd',
        'latency_ms',
        'verified_at',
        'verified_by',
    ];

    protected function casts(): array
    {
        return [
            'detected_type' => DocumentType::class,
            'fields' => 'array',
            'confidence' => 'array',
            'missing_fields' => 'array',
            'warnings' => 'array',
            'estimated_cost_usd' => 'decimal:6',
            'verified_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function field(string $name): mixed
    {
        return $this->fields[$name] ?? null;
    }

    /** "total" or "tax" as Money. */
    public function money(string $name): ?Money
    {
        $cents = $this->fields["{$name}_cents"] ?? null;

        return $cents === null
            ? null
            : new Money((int) $cents, $this->fields['currency'] ?? config('documind.default_currency'));
    }

    public function confidenceLevel(string $field): string
    {
        $score = (float) ($this->confidence[$field] ?? 0);

        return match (true) {
            $score >= 0.85 => 'high',
            $score >= 0.6 => 'medium',
            default => 'low',
        };
    }

    public function usedAi(): bool
    {
        return $this->provider !== 'rules';
    }
}
