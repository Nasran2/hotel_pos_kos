<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::query()->where('username', 'admin')->firstOrFail();
    $this->actingAs($this->admin);
    $product = DB::table('products')->first();
    $this->payload = [
        'customer_id' => DB::table('customers')->where('is_walk_in', true)->value('id'),
        'items' => [['id' => $product->id, 'name' => $product->name, 'quantity' => 1, 'price' => 100, 'note' => 'No chilli']],
        'note' => 'Pack separately',
    ];
});

it('sends a takeaway ticket and publishes preparation and ready states to POS', function (): void {
    $stock = DB::table('products')->where('id', $this->payload['items'][0]['id'])->value('stock_quantity');
    $sent = $this->postJson(route('pos.kitchen.send'), $this->payload)->assertSuccessful()->assertJsonPath('formatted_token', '01');
    $orderId = $sent->json('kitchen_order_id');
    $this->getJson(route('kitchen.feed'))->assertSuccessful()
        ->assertJsonPath('orders.0.status', 'queued')->assertJsonPath('orders.0.hold_id', $sent->json('hold_id'))
        ->assertJsonPath('orders.0.items.0.note', 'No chilli');
    $this->postJson(route('kitchen.status', $orderId), ['status' => 'preparing', 'revision' => 1])->assertSuccessful();
    $this->postJson(route('kitchen.status', $orderId), ['status' => 'ready', 'revision' => 1])->assertSuccessful();
    $this->getJson(route('kitchen.feed'))->assertSuccessful()->assertJsonPath('orders.0.status', 'ready');
    $this->assertDatabaseHas('kitchen_orders', ['id' => $orderId, 'status' => 'ready']);
    expect(DB::table('kitchen_orders')->where('id', $orderId)->value('ready_at'))->not->toBeNull();
    $this->postJson(route('pos.kitchen.served', $orderId), ['status' => 'served', 'revision' => 1])->assertSuccessful();
    $this->getJson(route('kitchen.feed'))->assertSuccessful()->assertJsonCount(0, 'orders');
    expect(DB::table('products')->where('id', $this->payload['items'][0]['id'])->value('stock_quantity'))->toBe($stock)
        ->and(DB::table('sales')->count())->toBe(0);
});

it('does not duplicate or reset an unchanged ticket and revisions reject stale actions', function (): void {
    $sent = $this->postJson(route('pos.kitchen.send'), $this->payload)->assertSuccessful();
    $id = $sent->json('kitchen_order_id');
    $this->postJson(route('kitchen.status', $id), ['status' => 'ready', 'revision' => 1])->assertSuccessful();
    $this->payload['hold_id'] = $sent->json('hold_id');
    $resent = $this->postJson(route('pos.kitchen.send'), $this->payload)->assertSuccessful()->assertJsonPath('kitchen_order_id', $id);
    $this->assertDatabaseHas('kitchen_orders', ['id' => $id, 'status' => 'ready', 'revision' => 1]);
    $this->payload['hold_id'] = $resent->json('hold_id');
    $this->payload['items'][0]['quantity'] = 2;
    $this->postJson(route('pos.kitchen.send'), $this->payload)->assertSuccessful();
    $this->assertDatabaseCount('kitchen_orders', 1);
    $this->getJson(route('kitchen.feed'))->assertSuccessful()->assertJsonPath('orders.0.status', 'queued')
        ->assertJsonPath('orders.0.revision', 2)->assertJsonPath('orders.0.previous_items.0.quantity', 1);
    $this->postJson(route('kitchen.status', $id), ['status' => 'ready', 'revision' => 1])->assertUnprocessable()->assertJsonValidationErrors('revision');
    $this->postJson(route('pos.kitchen.served', $id), ['status' => 'served', 'revision' => 2])->assertUnprocessable()->assertJsonValidationErrors('status');
});

it('keeps the ticket through print, transfer, and payment', function (): void {
    DB::table('registers')->insert(['user_id' => $this->admin->id, 'opening_cash' => 0, 'opened_at' => now(), 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
    $tables = DB::table('restaurant_tables')->limit(2)->pluck('id');
    $this->payload['table_id'] = $tables[0];
    $sent = $this->postJson(route('pos.kitchen.send'), $this->payload)->assertSuccessful();
    $this->assertDatabaseHas('hold_orders', ['id' => $sent->json('hold_id'), 'status' => 'hold', 'restaurant_table_id' => $tables[0], 'deleted_at' => null]);
    $this->assertDatabaseHas('restaurant_tables', ['id' => $tables[0], 'status' => 'hold']);
    $this->getJson(route('pos.resume', $tables[0]))->assertSuccessful()->assertJsonPath('hold.status', 'hold')->assertJsonCount(1, 'items');
    $this->postJson(route('pos.transfer'), ['from_table_id' => $tables[0], 'to_table_id' => $tables[1]])->assertSuccessful();
    $this->getJson(route('kitchen.feed'))->assertJsonPath('orders.0.table', DB::table('restaurant_tables')->where('id', $tables[1])->value('number'));
    $this->payload['table_id'] = $tables[1];
    $this->payload['hold_id'] = $sent->json('hold_id');
    $printed = $this->postJson(route('pos.print'), $this->payload)->assertSuccessful();
    $this->payload['hold_id'] = $printed->json('hold_id');
    $this->postJson(route('pos.pay'), [...$this->payload, 'payment_method' => 'cash', 'received_amount' => 200])->assertSuccessful();
    $this->getJson(route('kitchen.feed'))->assertSuccessful()->assertJsonPath('orders.0.status', 'queued')->assertJsonPath('orders.0.hold_id', null);
    $this->assertDatabaseCount('kitchen_orders', 1);
});

it('cancels kitchen work when a held order is cancelled', function (): void {
    $sent = $this->postJson(route('pos.kitchen.send'), $this->payload)->assertSuccessful();
    $this->deleteJson(route('pos.hold.cancel', $sent->json('hold_id')))->assertSuccessful();
    $this->assertDatabaseHas('kitchen_orders', ['id' => $sent->json('kitchen_order_id'), 'status' => 'stop_requested']);
    $this->getJson(route('kitchen.feed'))->assertJsonPath('orders.0.status', 'stop_requested');
});

it('restricts kitchen staff to kitchen work and routes their login to the kitchen', function (): void {
    $sent = $this->postJson(route('pos.kitchen.send'), $this->payload)->assertSuccessful();
    $staff = User::factory()->create(['username' => 'chef', 'password' => 'password']);
    $staff->roles()->attach(Role::query()->where('name', 'Kitchen Staff')->firstOrFail());
    $this->actingAs($staff);
    $this->get(route('kitchen.index'))->assertRedirect(route('kod.index'));
    $this->getJson(route('kitchen.feed'))->assertSuccessful();
    $this->postJson(route('kitchen.status', $sent->json('kitchen_order_id')), ['status' => 'ready', 'revision' => 1])->assertSuccessful();
    $this->postJson(route('pos.kitchen.send'), $this->payload)->assertForbidden();
    $this->postJson(route('pos.kitchen.served', $sent->json('kitchen_order_id')), ['status' => 'served', 'revision' => 1])->assertForbidden();
    $this->post(route('logout'))->assertRedirect(route('login'));
    $this->post(route('login.store'), ['username' => 'chef', 'password' => 'password'])->assertRedirect(route('kitchen.index'));
});

it('rejects invalid carts, unauthorised access, and invalid status requests', function (): void {
    $this->postJson(route('pos.kitchen.send'), ['items' => []])->assertUnprocessable()->assertJsonValidationErrors(['items', 'customer_id']);
    $sent = $this->postJson(route('pos.kitchen.send'), $this->payload)->assertSuccessful();
    $this->postJson(route('kitchen.status', $sent->json('kitchen_order_id')), ['status' => 'served', 'revision' => 1])->assertUnprocessable();
    $this->actingAs(User::factory()->create());
    $this->getJson(route('kitchen.feed'))->assertForbidden();
    $this->get(route('kitchen.index'))->assertRedirect(route('kod.index'));
    $this->postJson(route('kitchen.status', $sent->json('kitchen_order_id')), ['status' => 'ready', 'revision' => 1])->assertForbidden();
});

it('renders the redesigned dashboard and POS queue', function (): void {
    $this->get(route('dashboard'))->assertSuccessful()->assertSee('Every detail. One place.');
    $this->get(route('pos.index'))->assertSuccessful()->assertSee('Send to kitchen')->assertSee('Ready to serve');
});

it('allows additional dishes on a served table ticket and validates active products', function (): void {
    $sent = $this->postJson(route('pos.kitchen.send'), $this->payload)->assertSuccessful();
    $id = $sent->json('kitchen_order_id');
    $this->postJson(route('kitchen.status', $id), ['status' => 'ready', 'revision' => 1])->assertSuccessful();
    $this->postJson(route('pos.kitchen.served', $id), ['status' => 'served', 'revision' => 1])->assertSuccessful();
    $this->payload['hold_id'] = $sent->json('hold_id');
    $this->payload['items'][0]['quantity'] = 2;
    $resent = $this->postJson(route('pos.kitchen.send'), $this->payload)->assertSuccessful();
    $this->assertDatabaseHas('kitchen_orders', ['id' => $id, 'status' => 'queued', 'revision' => 2, 'served_at' => null]);
    $this->getJson(route('kitchen.feed'))->assertJsonPath('orders.0.previous_items.0.quantity', 1);
    DB::table('products')->where('id', $this->payload['items'][0]['id'])->update(['is_active' => false]);
    $this->payload['hold_id'] = $resent->json('hold_id');
    $this->postJson(route('pos.kitchen.send'), $this->payload)->assertUnprocessable()->assertJsonValidationErrors('items.0.id');
});

it('rejects resending cancelled tickets and missing kitchen orders', function (): void {
    $sent = $this->postJson(route('pos.kitchen.send'), $this->payload)->assertSuccessful();
    $this->postJson(route('kitchen.status', $sent->json('kitchen_order_id')), ['status' => 'cancelled', 'revision' => 1])->assertSuccessful();
    $this->payload['hold_id'] = $sent->json('hold_id');
    $this->postJson(route('pos.kitchen.send'), $this->payload)->assertUnprocessable()->assertJsonValidationErrors('order');
    $this->assertDatabaseHas('hold_orders', ['id' => $sent->json('hold_id'), 'status' => 'hold', 'deleted_at' => null]);
    $this->postJson(route('kitchen.status', 99999), ['status' => 'ready', 'revision' => 1])->assertNotFound();
    $this->postJson('/kitchen/orders/invalid/status', ['status' => 'ready', 'revision' => 1])->assertNotFound();
});
