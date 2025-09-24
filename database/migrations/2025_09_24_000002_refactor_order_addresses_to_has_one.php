<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_addresses', function (Blueprint $table) {
            // Drop morph columns and switch to order_id
            if (Schema::hasColumn('order_addresses', 'addressable_type')) {
                $table->dropColumn('addressable_type');
            }
            if (Schema::hasColumn('order_addresses', 'addressable_id')) {
                $table->renameColumn('addressable_id', 'order_id');
            } else {
                // If for some reason addressable_id doesn't exist, ensure order_id exists
                if (!Schema::hasColumn('order_addresses', 'order_id')) {
                    $table->unsignedBigInteger('order_id')->nullable()->index();
                }
            }

            // Add FK if possible (works on MySQL/PGSQL)
            if (!Schema::hasColumn('order_addresses', 'order_id')) {
                return;
            }
            // Try to add foreign key safely
            try {
                $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            } catch (\Throwable $e) {
                // Ignore if FK already exists or not supported in current state
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_addresses', function (Blueprint $table) {
            // Drop FK if exists
            try {
                $table->dropForeign(['order_id']);
            } catch (\Throwable $e) {}

            if (Schema::hasColumn('order_addresses', 'order_id')) {
                $table->renameColumn('order_id', 'addressable_id');
            }
            // Restore morph columns
            if (!Schema::hasColumn('order_addresses', 'addressable_type')) {
                $table->string('addressable_type')->nullable();
            }
        });
    }
};


