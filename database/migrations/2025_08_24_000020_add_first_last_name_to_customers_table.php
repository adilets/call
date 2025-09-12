<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
        });

        // Best-effort backfill: split existing name into first/last by first space
        $customers = DB::table('customers')->select('id', 'name')->get();
        foreach ($customers as $c) {
            if (!empty($c->name)) {
                $parts = preg_split('/\s+/', trim($c->name), 2);
                $first = $parts[0] ?? null;
                $last  = $parts[1] ?? null;

                DB::table('customers')->where('id', $c->id)->update([
                    'first_name' => $first,
                    'last_name'  => $last,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name']);
        });
    }
};


