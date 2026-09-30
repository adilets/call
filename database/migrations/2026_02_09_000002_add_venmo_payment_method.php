<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('payment_methods')->where('code', 'venmo')->exists();
        if (!$exists) {
            DB::table('payment_methods')->insert([
                'name' => 'Venmo',
                'code' => 'venmo',
                'is_active' => true,
                'sort_order' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('payment_methods')->where('code', 'venmo')->delete();
    }
};
