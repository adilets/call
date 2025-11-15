<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `orders` MODIFY `status` ENUM('new','processing','partially_paid','paid','shipped','delivered','cancelled','expired') NOT NULL DEFAULT 'new'");
        }
        // On sqlite/pgsql, do nothing – sqlite treats enum as text; pgsql enum change needs a different path.
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `orders` MODIFY `status` ENUM('new','processing','paid','shipped','delivered','cancelled') NOT NULL DEFAULT 'new'");
        }
    }
};


