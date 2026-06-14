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
            $table->string('invoice_no')->nullable()->after('restaurant_table_id')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hold_orders', function (Blueprint $table) {
            $table->dropIndex(['invoice_no']);
            $table->dropColumn('invoice_no');
        });
    }
};
