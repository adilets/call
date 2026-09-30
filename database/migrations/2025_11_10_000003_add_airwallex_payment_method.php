<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('payment_methods')->where('code', 'airwallex')->exists();
        if (!$exists) {
            DB::table('payment_methods')->insert([
                'name' => 'Airwallex',
                'code' => 'airwallex',
                'is_active' => true,
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('payment_methods')->where('code', 'airwallex')->delete();
    }
};


