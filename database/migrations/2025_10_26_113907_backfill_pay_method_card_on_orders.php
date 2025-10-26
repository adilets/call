<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('orders')
            ->whereNull('pay_method')
            ->update(['pay_method' => 'card']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op: keep backfill
    }
};
