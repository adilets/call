<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('client_id')->constrained()->nullOnDelete();
        });

        Schema::table('shipping_methods', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('client_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('shipping_methods', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};


