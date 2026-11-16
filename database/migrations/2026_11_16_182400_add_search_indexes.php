<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        DB::statement('CREATE INDEX documents_original_name_trgm_index ON documents USING gin (original_name gin_trgm_ops)');
        DB::statement('CREATE INDEX invoices_supplier_name_trgm_index ON invoices USING gin (supplier_name gin_trgm_ops)');
        DB::statement('CREATE INDEX invoices_invoice_number_trgm_index ON invoices USING gin (invoice_number gin_trgm_ops)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS invoices_invoice_number_trgm_index');
        DB::statement('DROP INDEX IF EXISTS invoices_supplier_name_trgm_index');
        DB::statement('DROP INDEX IF EXISTS documents_original_name_trgm_index');
    }
};
