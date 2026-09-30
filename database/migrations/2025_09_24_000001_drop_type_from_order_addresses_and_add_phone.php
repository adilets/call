<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_addresses', function (Blueprint $table) {
            if (Schema::hasColumn('order_addresses', 'type')) {
                $table->dropColumn('type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_addresses', function (Blueprint $table) {
            if (!Schema::hasColumn('order_addresses', 'type')) {
                $table->string('type')->nullable()->after('addressable_id');
            }
        });
    }
};


