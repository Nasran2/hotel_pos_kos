<?php

use App\Models\OnlineOrder;
use App\Models\OnlineOrderSource;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    // Run seeders to set up test data
    $this->seed(DatabaseSeeder::class);
    
    $this->user = User::where('username', 'admin')->firstOrFail();
    $this->actingAs($this->user);

    // Create a register for the user
    DB::table('registers')->insertGetId([
        'user_id' => $this->user->id,
        'opening_cash' => 1000,
        'opened_at' => now(),
        'status' => 'open',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Create test platform source
    $this->source = OnlineOrderSource::create([
        'name' => 'TestPlatform',
        'commission_type' => 'percentage',
        'commission_value' => 18,
        'is_active' => true,
    ]);

    // Create test products
    DB::table('products')->insert([
        ['name' => 'Test Item 1', 'selling_price' => 400, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ['name' => 'Test Item 2', 'selling_price' => 450, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);
});

test('online order requires platform selection', function () {
    $response = $this->post(route('online-orders.store'), [
        'online_order_source_id' => '',
        'order_reference' => 'TEST-001',
        'customer_name' => 'John Doe',
        'customer_phone' => '0771234567',
        'delivery_address' => 'Test Address',
        'discount_type' => 'fixed',
        'discount_value' => 0,
        'delivery_charge' => 0,
        'payment_status' => 'cash_on_delivery',
        'payment_method' => 'online',
        'order_status' => 'new',
        'paid_amount' => 0,
        'notes' => '',
        'items_payload' => json_encode([
            ['id' => 1, 'qty' => 1, 'price' => 400],
        ]),
    ]);

    $response->assertSessionHasErrors('online_order_source_id');
});

test('online order with valid platform selection creates order', function () {
    $response = $this->post(route('online-orders.store'), [
        'online_order_source_id' => $this->source->id,
        'order_reference' => 'TEST-' . time(),
        'customer_name' => 'John Doe',
        'customer_phone' => '0771234567',
        'delivery_address' => 'Test Address',
        'discount_type' => 'fixed',
        'discount_value' => 0,
        'delivery_charge' => 0,
        'payment_status' => 'cash_on_delivery',
        'payment_method' => 'online',
        'order_status' => 'new',
        'paid_amount' => 0,
        'notes' => 'Test notes',
        'items_payload' => json_encode([
            ['id' => 1, 'qty' => 1, 'price' => 400],
        ]),
    ]);

    // Check for validation errors
    if ($response->getSession()->has('errors')) {
        $errors = $response->getSession()->get('errors');
        $errorMessages = is_array($errors) ? $errors : $errors->getMessages();
        $this->fail('Validation errors: ' . json_encode($errorMessages));
    }
    
    $response->assertRedirect();
    $this->assertDatabaseHas('online_orders', [
        'online_order_source_id' => $this->source->id,
        'customer_name' => 'John Doe',
    ]);
});

test('online order requires customer name', function () {
    $response = $this->post(route('online-orders.store'), [
        'online_order_source_id' => $this->source->id,
        'order_reference' => 'TEST-001',
        'customer_name' => '',
        'customer_phone' => '0771234567',
        'delivery_address' => 'Test Address',
        'discount_type' => 'fixed',
        'discount_value' => 0,
        'delivery_charge' => 0,
        'payment_status' => 'cash_on_delivery',
        'payment_method' => 'online',
        'order_status' => 'new',
        'paid_amount' => 0,
        'notes' => '',
        'items_payload' => json_encode([
            ['id' => 1, 'qty' => 1, 'price' => 400],
        ]),
    ]);

    $response->assertSessionHasErrors('customer_name');
});

test('online order requires order reference', function () {
    $response = $this->post(route('online-orders.store'), [
        'online_order_source_id' => $this->source->id,
        'order_reference' => '',
        'customer_name' => 'John Doe',
        'customer_phone' => '0771234567',
        'delivery_address' => 'Test Address',
        'discount_type' => 'fixed',
        'discount_value' => 0,
        'delivery_charge' => 0,
        'payment_status' => 'cash_on_delivery',
        'payment_method' => 'online',
        'order_status' => 'new',
        'paid_amount' => 0,
        'notes' => '',
        'items_payload' => json_encode([
            ['id' => 1, 'qty' => 1, 'price' => 400],
        ]),
    ]);

    $response->assertSessionHasErrors('order_reference');
});
