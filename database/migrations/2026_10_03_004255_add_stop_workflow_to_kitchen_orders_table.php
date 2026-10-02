<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kitchen_orders', function (Blueprint $table): void {
            $table->foreignId('stop_requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('stop_reason')->nullable();
            $table->timestamp('stop_requested_at')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('kitchen_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('stop_requested_by');
            $table->dropColumn(['stop_reason', 'stop_requested_at', 'stopped_at', 'deleted_at']);
        });
    }
};
