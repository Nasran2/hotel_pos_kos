<?php

use App\Models\OnlineOrder;
use App\Models\OnlineOrderSource;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::query()->where('username', 'admin')->firstOrFail();
    $this->actingAs($this->admin);
    DB::table('registers')->insert(['user_id' => $this->admin->id, 'opening_cash' => 1000, 'opened_at' => now(), 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
    $this->source = OnlineOrderSource::query()->where('name', 'PickMe Food')->firstOrFail();
    $this->productId = DB::table('products')->insertGetId([
        'name' => 'Kitchen meal', 'selling_price' => 450, 'cost_price' => 200, 'maintain_stock' => true,
        'stock_quantity' => 10, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->payload = [
        'online_order_source_id' => $this->source->id, 'order_reference' => 'PICK-10025',
        'customer_name' => 'Guest One', 'customer_phone' => '0771234567', 'delivery_address' => '42 Guest Street',
        'discount_type' => 'percentage', 'discount_value' => 10, 'delivery_charge' => 50,
        'payment_status' => 'pending', 'payment_method' => 'platform_payment', 'order_status' => 'new',
        'notes' => 'No chilli · pack separately',
        'items_payload' => json_encode([['id' => $this->productId, 'qty' => 2, 'price' => 500]]),
    ];
});

test('accepted online orders use one token and kitchen ticket with correct totals and platform metadata', function (): void {
    $this->post(route('online-orders.store'), [...$this->payload, 'payment_status' => 'partially_paid', 'paid_amount' => 100])
        ->assertRedirect(route('online-orders.index'))->assertSessionHasNoErrors();
    $order = OnlineOrder::query()->firstOrFail();
    $ticket = DB::table('kitchen_orders')->first();
    $sale = DB::table('sales')->where('id', $order->sale_id)->first();
    expect($order->total)->toBe(950.0)->and($order->commission_amount)->toBe(162.0)
        ->and($order->paid_amount)->toBe(100.0)->and($order->balance_amount)->toBe(850.0)
        ->and((float) $sale->profit)->toBe(338.0)
        ->and((int) $ticket->order_token_id)->toBe($order->order_token_id)
        ->and((int) $sale->order_token_id)->toBe($order->order_token_id)
        ->and($ticket->status)->toBe('queued')
        ->and(json_decode($ticket->items, true)[0]['name'])->toBe('Kitchen meal')
        ->and($ticket->note)->toBe($this->payload['notes']);
    $this->getJson(route('kitchen.feed'))->assertSuccessful()->assertJsonPath('orders.0.platform', 'PickMe Food')
        ->assertJsonPath('orders.0.order_reference', 'PICK-10025');
    $this->post(route('online-orders.store'), $this->payload)->assertSessionHasErrors('order_reference');
    $this->assertDatabaseCount('kitchen_orders', 1);
    $this->assertDatabaseCount('online_orders', 1);
});

test('PIN kitchen updates synchronize online orders and sales and deduct stock only once', function (): void {
    $this->post(route('online-orders.store'), $this->payload)->assertSessionHasNoErrors();
    $order = OnlineOrder::query()->firstOrFail();
    $ticket = DB::table('kitchen_orders')->first();
    $this->post(route('logout'));
    $this->postJson(route('kod.unlock'), ['pin' => '0000'])->assertSuccessful();
    $this->postJson(route('kod.status', $ticket->id), ['status' => 'preparing', 'revision' => 1])->assertSuccessful();
    $this->assertDatabaseHas('online_orders', ['id' => $order->id, 'order_status' => 'preparing']);
    $this->assertDatabaseHas('sales', ['id' => $order->sale_id, 'online_order_status' => 'preparing']);
    $this->postJson(route('kod.status', $ticket->id), ['status' => 'ready', 'revision' => 1])->assertSuccessful();
    $this->postJson(route('kod.status', $ticket->id), ['status' => 'ready', 'revision' => 1])->assertSuccessful();
    expect((float) DB::table('products')->where('id', $this->productId)->value('stock_quantity'))->toBe(8.0)
        ->and(DB::table('stock_movements')->where('source_type', 'online_order')->count())->toBe(1);
    $this->actingAs($this->admin)->getJson(route('online-orders.feed', ['ids' => [$order->id]]))
        ->assertSuccessful()->assertJsonPath('orders.0.order_status', 'ready')->assertJsonPath('orders.0.kitchen_status', 'ready');
    $this->post(route('online-orders.status', $order->id), ['order_status' => 'out_for_delivery'])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('kitchen_orders', ['id' => $ticket->id, 'status' => 'served']);
    $this->post(route('online-orders.status', $order->id), ['order_status' => 'delivered'])->assertSessionHasNoErrors();
    $this->post(route('online-orders.status', $order->id), ['order_status' => 'new'])->assertSessionHasErrors('order_status');
});

test('cashier cancellation raises a live kitchen stop request and reverses stock and commission only once', function (): void {
    $this->post(route('online-orders.store'), [...$this->payload, 'payment_status' => 'paid'])->assertSessionHasNoErrors();
    $order = OnlineOrder::query()->firstOrFail();
    $ticket = DB::table('kitchen_orders')->first();
    $this->postJson(route('kitchen.status', $ticket->id), ['status' => 'preparing', 'revision' => 1])->assertSuccessful();
    $this->post(route('online-orders.status', $order->id), ['order_status' => 'cancelled'])->assertSessionHasNoErrors();
    $this->post(route('online-orders.status', $order->id), ['order_status' => 'cancelled'])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('kitchen_orders', ['id' => $ticket->id, 'status' => 'stop_requested']);
    $this->getJson(route('kitchen.feed'))->assertJsonPath('orders.0.stop_reason', 'Online order PICK-10025 cancelled by cashier. Stop preparing this order.');
    expect((float) DB::table('products')->where('id', $this->productId)->value('stock_quantity'))->toBe(10.0)
        ->and(DB::table('stock_movements')->where('type', 'online_order_cancel')->count())->toBe(1)
        ->and(DB::table('bank_transactions')->count())->toBe(0);
    $this->post(route('online-orders.status', $order->id), ['order_status' => 'ready'])->assertSessionHasErrors('order_status');
    $this->post(route('online-orders.payment', $order->id), ['amount' => 10, 'payment_method' => 'cash', 'payment_date' => now()])->assertSessionHasErrors('amount');
    $this->postJson(route('kitchen.status', $ticket->id), ['status' => 'stopped', 'revision' => 1])->assertSuccessful();
});

test('later payments use the current outstanding balance and cannot overpay', function (): void {
    $this->post(route('online-orders.store'), $this->payload)->assertSessionHasNoErrors();
    $order = OnlineOrder::query()->firstOrFail();
    foreach ([200, 900] as $amount) {
        $this->post(route('online-orders.payment', $order->id), ['amount' => $amount, 'payment_method' => 'cash', 'payment_date' => now()])->assertSessionHasNoErrors();
    }
    expect($order->fresh()->paid_amount)->toBe(950.0)->and($order->fresh()->balance_amount)->toBe(0.0)
        ->and((float) DB::table('online_order_payments')->sum('amount'))->toBe(950.0)
        ->and((float) DB::table('sale_payments')->sum('amount'))->toBe(950.0);
    $this->post(route('online-orders.payment', $order->id), ['amount' => 10, 'payment_method' => 'cash', 'payment_date' => now()])->assertSessionHasErrors('amount');
});

test('malformed item payloads cannot create orders or kitchen tickets', function (string $payload): void {
    $this->postJson(route('online-orders.store'), [...$this->payload, 'items_payload' => $payload])->assertUnprocessable();
    $this->assertDatabaseCount('online_orders', 0);
    $this->assertDatabaseCount('kitchen_orders', 0);
})->with(['invalid JSON' => '{', 'scalar' => '42', 'empty' => '[]', 'scalar item' => '[1]', 'missing fields' => '[{"id":1}]', 'negative quantity' => '[{"id":1,"qty":-1,"price":5}]']);

test('inactive sources and unavailable products are rejected before creating any records', function (): void {
    $this->source->update(['is_active' => false]);
    $this->postJson(route('online-orders.store'), $this->payload)->assertUnprocessable()->assertJsonValidationErrors('online_order_source_id');
    $this->source->update(['is_active' => true]);
    DB::table('products')->where('id', $this->productId)->update(['deleted_at' => now()]);
    $this->postJson(route('online-orders.store'), $this->payload)->assertUnprocessable()->assertJsonValidationErrors('items.0.id');
    $this->assertDatabaseCount('online_orders', 0);
});

test('insufficient stock rolls back both kitchen and online preparation status', function (): void {
    $this->post(route('online-orders.store'), [...$this->payload, 'items_payload' => json_encode([['id' => $this->productId, 'qty' => 20, 'price' => 500]])])->assertSessionHasNoErrors();
    $order = OnlineOrder::query()->firstOrFail();
    $ticket = DB::table('kitchen_orders')->first();
    $this->postJson(route('kitchen.status', $ticket->id), ['status' => 'ready', 'revision' => 1])->assertUnprocessable()->assertJsonValidationErrors('order');
    $this->assertDatabaseHas('kitchen_orders', ['id' => $ticket->id, 'status' => 'queued']);
    $this->assertDatabaseHas('online_orders', ['id' => $order->id, 'order_status' => 'new', 'stock_reduced_at' => null]);
});

test('legacy orders can be sent once without creating a second bill', function (): void {
    $this->post(route('online-orders.store'), $this->payload)->assertSessionHasNoErrors();
    $order = OnlineOrder::query()->firstOrFail();
    DB::table('kitchen_orders')->delete();
    $this->post(route('online-orders.kitchen', $order->id))->assertSessionHasNoErrors();
    $this->post(route('online-orders.kitchen', $order->id))->assertSessionHasNoErrors();
    $this->assertDatabaseCount('kitchen_orders', 1);
    $this->assertDatabaseCount('sales', 1);
    $this->assertDatabaseCount('online_orders', 1);
});

test('online view provides real controls and protects tracking and mutations', function (): void {
    $this->get(route('online-orders.index'))->assertSuccessful()->assertSee('Every channel. One kitchen.')
        ->assertSee('Track online orders')->assertSee('Accept &amp; send to kitchen', false)->assertDontSee('Map Preview');
    $this->get(route('online-orders.index', ['from' => 'invalid']))->assertSessionHasErrors('from');
    $this->getJson(route('online-orders.feed', ['ids' => ['invalid']]))->assertUnprocessable();
    $this->actingAs(User::factory()->create())->getJson(route('online-orders.feed', ['ids' => [1]]))->assertForbidden();
    $this->postJson(route('online-orders.store'), $this->payload)->assertForbidden();
});
