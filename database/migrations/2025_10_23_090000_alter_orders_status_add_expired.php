<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver != 'mysql') {
            // PostgreSQL: $table->enum created a CHECK constraint (orders_status_check)
            // Extend allowed values to include 'expired'
            DB::statement("ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check;");
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_check CHECK (status IN ('new','processing','paid','shipped','delivered','cancelled','expired'));");
            DB::statement("ALTER TABLE orders ALTER COLUMN status SET DEFAULT 'new';");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver != 'mysql') {
            // Revert to the original enum set (without 'expired')
            DB::statement("ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check;");
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_check CHECK (status IN ('new','processing','paid','shipped','delivered','cancelled'));");
            DB::statement("ALTER TABLE orders ALTER COLUMN status SET DEFAULT 'new';");
        }
    }
};


