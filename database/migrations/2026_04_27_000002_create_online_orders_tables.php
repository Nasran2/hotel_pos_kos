<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('online_order_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('custom')->index();
            $table->string('commission_type')->default('percentage');
            $table->decimal('commission_value', 12, 2)->default(0);
            $table->string('default_payment_method')->default('platform_payment');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('online_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('register_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('online_order_source_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->string('order_reference')->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_phone')->nullable();
            $table->text('delivery_address')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->string('discount_type')->nullable();
            $table->decimal('discount_value', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('delivery_charge', 12, 2)->default(0);
            $table->string('commission_type')->default('percentage');
            $table->decimal('commission_value', 12, 2)->default(0);
            $table->decimal('commission_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('balance_amount', 12, 2)->default(0);
            $table->string('payment_status')->default('pending')->index();
            $table->string('payment_method')->default('platform_payment')->index();
            $table->string('order_status')->default('new')->index();
            $table->timestamp('stock_reduced_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('online_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('online_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->decimal('qty', 12, 3);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('online_order_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('online_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('register_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('payment_method')->default('cash');
            $table->dateTime('payment_date');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->string('order_channel')->default('pos')->after('status')->index();
            $table->foreignId('online_order_source_id')->nullable()->after('order_channel')->constrained('online_order_sources')->nullOnDelete();
            $table->string('online_order_reference')->nullable()->after('online_order_source_id')->index();
            $table->string('online_order_status')->nullable()->after('online_order_reference')->index();
            $table->string('online_payment_status')->nullable()->after('online_order_status')->index();
            $table->timestamp('stock_reduced_at')->nullable()->after('online_payment_status');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['online_order_source_id']);
            $table->dropColumn([
                'order_channel',
                'online_order_source_id',
                'online_order_reference',
                'online_order_status',
                'online_payment_status',
                'stock_reduced_at',
            ]);
        });

        Schema::dropIfExists('online_order_payments');
        Schema::dropIfExists('online_order_items');
        Schema::dropIfExists('online_orders');
        Schema::dropIfExists('online_order_sources');
    }
};
