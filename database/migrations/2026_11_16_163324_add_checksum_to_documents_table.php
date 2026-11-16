<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // SHA-256 of the file's bytes: identical files always produce the same value.
            $table->string('checksum', 64)->nullable();
            $table->index(['business_id', 'checksum']);
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['business_id', 'checksum']);
            $table->dropColumn('checksum');
        });
    }
};
