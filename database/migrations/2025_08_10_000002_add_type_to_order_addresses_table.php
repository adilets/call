<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_addresses', function (Blueprint $table) {
            $table->string('type', 16)->default('billing')->after('id');
            $table->index(['addressable_id', 'addressable_type', 'type'], 'order_addresses_addressable_type_idx');
        });
    }

    public function down(): void
    {
        Schema::table('order_addresses', function (Blueprint $table) {
            $table->dropIndex('order_addresses_addressable_type_idx');
            $table->dropColumn('type');
        });
    }
};


