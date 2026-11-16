<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per extraction run. Unverified until a person checks it (Week 8).
        Schema::create('document_extractions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 30);   // "gemini" or "rules"
            $table->string('model')->nullable();
            $table->string('detected_type', 20);
            $table->jsonb('fields');          // supplier_name, document_number, dates, currency, *_cents
            $table->jsonb('confidence');
            $table->jsonb('missing_fields');
            $table->jsonb('warnings');
            $table->text('summary')->nullable();
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('estimated_cost_usd', 10, 6)->default(0);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['document_id', 'created_at']);
            $table->index(['business_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_extractions');
    }
};
