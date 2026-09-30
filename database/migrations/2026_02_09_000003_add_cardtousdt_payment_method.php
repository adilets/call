<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('payment_methods')->where('code', 'cardtousdt')->exists();
        if (!$exists) {
            DB::table('payment_methods')->insert([
                'name' => 'CardToUSDT',
                'code' => 'cardtousdt',
                'is_active' => true,
                'sort_order' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('payment_methods')->where('code', 'cardtousdt')->delete();
    }
};
