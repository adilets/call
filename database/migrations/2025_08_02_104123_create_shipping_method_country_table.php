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
        Schema::create('shipping_method_country', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_method_id')->constrained()->onDelete('cascade');
            $table->string('country_code', 2);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_method_country');
    }
};
