<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);

    $this->user = User::query()->where('username', 'admin')->firstOrFail();
    $this->actingAs($this->user);

    $this->customerId = DB::table('customers')->insertGetId([
        'name' => 'Print Bill Customer',
        'phone' => '0771234567',
        'is_active' => true,
        'is_walk_in' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->product = DB::table('products')->first();
});

test('it stores edited temporary line changes when printing a bill', function (): void {
    $response = $this->postJson(route('pos.print'), [
        'customer_id' => $this->customerId,
        'discount_amount' => 0,
        'items' => [[
            'id' => $this->product->id,
            'name' => 'Special Kothu Name',
            'quantity' => 2,
            'price' => 400,
            'discount_amount' => 50,
            'discount_type' => 'fixed',
        ]],
    ])->assertSuccessful();

    $holdId = $response->json('hold_id');
    $line = DB::table('hold_order_items')->where('hold_order_id', $holdId)->first();

    expect($line)->not->toBeNull()
        ->and($line->product_name)->toBe('Special Kothu Name')
        ->and((float) $line->unit_price)->toBe(400.0)
        ->and((float) $line->discount_amount)->toBe(50.0)
        ->and((float) $line->line_total)->toBe(750.0);
});

test('it automatically falls back to the product name when a printed line item name is blank', function (): void {
    $response = $this->postJson(route('pos.print'), [
        'customer_id' => $this->customerId,
        'discount_amount' => 0,
        'items' => [[
            'id' => $this->product->id,
            'name' => '   ',
            'quantity' => 1,
            'price' => 550,
            'discount_amount' => 0,
        ]],
    ])->assertSuccessful();

    $holdId = $response->json('hold_id');
    $line = DB::table('hold_order_items')->where('hold_order_id', $holdId)->first();

    expect($line)->not->toBeNull()
        ->and($line->product_name)->toBe($this->product->name);
});
