<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kitchen_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_token_id')->unique()->constrained();
            $table->foreignId('created_by')->constrained('users');
            $table->string('status', 50)->default('queued');
            $table->json('items');
            $table->json('previous_items')->nullable();
            $table->text('note')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->timestamp('sent_at');
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('served_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kitchen_orders');
    }
};
