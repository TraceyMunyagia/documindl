<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('extraction_method', 20)->nullable();
            $table->text('processing_note')->nullable(); // advice when review is needed, e.g. "no readable text"
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['extraction_method', 'processing_note']);
        });
    }
};
