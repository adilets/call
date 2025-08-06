<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreignId('shipping_method_id')
                    ->constrained('shipping_methods')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();

                $table->foreignId('user_id')
                    ->constrained('users')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shipping_method_id');

            $table->dropConstrainedForeignId('user_id');
        });
    }
};
