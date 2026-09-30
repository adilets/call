<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_payment_method', function (Blueprint $table) {
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('payment_method_id');

            $table->primary(['client_id', 'payment_method_id']);

            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $table->foreign('payment_method_id')->references('id')->on('payment_methods')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_payment_method');
    }
};


