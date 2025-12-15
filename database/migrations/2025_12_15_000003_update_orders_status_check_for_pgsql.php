<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Allow new statuses in Postgres check constraint
            DB::statement('ALTER TABLE "orders" DROP CONSTRAINT IF EXISTS "orders_status_check"');
            DB::statement("ALTER TABLE \"orders\" ADD CONSTRAINT \"orders_status_check\" CHECK (status IN ('new','processing','partially_paid','paid','shipped','delivered','cancelled','expired'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Revert to original set (without partially_paid/expired)
            DB::statement('ALTER TABLE "orders" DROP CONSTRAINT IF EXISTS "orders_status_check"');
            DB::statement("ALTER TABLE \"orders\" ADD CONSTRAINT \"orders_status_check\" CHECK (status IN ('new','processing','paid','shipped','delivered','cancelled'))");
        }
    }
};


