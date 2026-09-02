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
        Schema::table('hold_orders', function (Blueprint $table) {
            $table->foreignId('order_token_id')
                ->nullable()
                ->after('invoice_no')
                ->constrained('order_tokens')
                ->restrictOnDelete();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('order_token_id')
                ->nullable()
                ->after('invoice_no')
                ->constrained('order_tokens')
                ->restrictOnDelete();
        });

        Schema::table('online_orders', function (Blueprint $table) {
            $table->foreignId('order_token_id')
                ->nullable()
                ->after('sale_id')
                ->constrained('order_tokens')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('online_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_token_id');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_token_id');
        });

        Schema::table('hold_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_token_id');
        });
    }
};
