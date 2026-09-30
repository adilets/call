<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phone is optional on the registration form, so the column must allow NULL.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('phone')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('phone')->nullable(false)->change();
        });
    }
};
