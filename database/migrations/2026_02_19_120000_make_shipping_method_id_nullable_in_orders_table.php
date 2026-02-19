<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `orders` MODIFY `shipping_method_id` BIGINT UNSIGNED NULL');
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE "orders" ALTER COLUMN "shipping_method_id" DROP NOT NULL');
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `orders` MODIFY `shipping_method_id` BIGINT UNSIGNED NOT NULL');
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE "orders" ALTER COLUMN "shipping_method_id" SET NOT NULL');
        }
    }
};

