<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('expected_amount', 12, 2)->nullable()->after('total_price');
            $table->string('expected_currency', 10)->nullable()->after('expected_amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['expected_amount', 'expected_currency']);
        });
    }
};
