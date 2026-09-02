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
        Schema::create('order_tokens', function (Blueprint $table) {
            $table->id();
            $table->date('token_date');
            $table->unsignedBigInteger('token_number');
            $table->timestamps();

            $table->unique(['token_date', 'token_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_tokens');
    }
};
