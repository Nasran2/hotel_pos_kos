<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);

    $this->user = User::query()->where('username', 'admin')->firstOrFail();
    $this->actingAs($this->user);

    $this->registerId = DB::table('registers')->insertGetId([
        'user_id' => $this->user->id,
        'opening_cash' => 0,
        'opened_at' => now(),
        'status' => 'open',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('settings')->updateOrInsert(
        ['group' => 'pos', 'key' => 'enable_service_charge'],
        ['value' => '0', 'type' => 'boolean', 'created_at' => now(), 'updated_at' => now()]
    );

    $this->customerId = DB::table('customers')->insertGetId([
        'name' => 'Test Due Customer',
        'phone' => '0770000000',
        'is_active' => true,
        'is_walk_in' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->walkInCustomerId = DB::table('customers')->where('is_walk_in', true)->value('id');
    $this->product = DB::table('products')->where('name', 'Fried Rice')->first();
});

it('turns a short cash payment into customer due balance', function (): void {
    $response = $this->postJson(route('pos.pay'), posPayload($this->customerId, $this->product->id, 2) + [
        'payment_method' => 'cash',
        'received_amount' => 500,
    ])
        ->assertSuccessful()
        ->assertJsonPath('status', 'due')
        ->assertJsonPath('paid_amount', 500)
        ->assertJsonPath('due_amount', 400)
        ->assertJsonPath('change_amount', 0);

    $saleId = $response->json('sale_id');
    $sale = DB::table('sales')->where('id', $saleId)->first();
    $payment = DB::table('sale_payments')->where('sale_id', $saleId)->first();

    expect($sale->status)->toBe('due')
        ->and((float) $sale->paid_amount)->toBe(500.0)
        ->and((float) $sale->due_amount)->toBe(400.0)
        ->and((float) $payment->amount)->toBe(500.0)
        ->and((float) $payment->received_amount)->toBe(500.0)
        ->and((float) DB::table('products')->where('id', $this->product->id)->value('stock_quantity'))->toBe(18.0);

    $this->get(route('backoffice.modules.index', 'customers'))
        ->assertSuccessful()
        ->assertSee('Due Amount')
        ->assertSee('400.00')
        ->assertDontSee('<th class="px-4 py-3">Address</th>', false)
        ->assertDontSee('<th class="px-4 py-3">Email</th>', false);

    $this->get(route('backoffice.modules.show', ['customers', $this->customerId]))
        ->assertSuccessful()
        ->assertSee('Due Balance')
        ->assertSee('Sales History')
        ->assertSee($sale->invoice_no);

    $this->get(route('backoffice.modules.show', ['products', $this->product->id]))
        ->assertSuccessful()
        ->assertSee('Sales History')
        ->assertSee($sale->invoice_no)
        ->assertSee('Stock Movement');
});

it('rejects a short payment for walk in customer', function (): void {
    $this->postJson(route('pos.pay'), posPayload($this->walkInCustomerId, $this->product->id, 2) + [
        'payment_method' => 'cash',
        'received_amount' => 500,
    ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Select a registered customer before leaving a due balance.');

    expect(DB::table('sales')->count())->toBe(0);
});

function posPayload(int $customerId, int $productId, int $quantity): array
{
    return [
        'customer_id' => $customerId,
        'waiter_id' => null,
        'table_id' => null,
        'hold_id' => null,
        'note' => null,
        'discount_amount' => 0,
        'items' => [
            [
                'id' => $productId,
                'name' => 'Fried Rice',
                'quantity' => $quantity,
                'price' => 450,
                'discount_amount' => 0,
            ],
        ],
    ];
}
