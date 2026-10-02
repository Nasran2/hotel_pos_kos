<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::query()->where('username', 'admin')->firstOrFail();
    $product = DB::table('products')->first();
    $this->payload = [
        'customer_id' => DB::table('customers')->where('is_walk_in', true)->value('id'),
        'table_id' => DB::table('restaurant_tables')->value('id'),
        'items' => [['id' => $product->id, 'name' => $product->name, 'quantity' => 1, 'price' => 100]],
    ];
    $this->actingAs($this->admin);
    $this->sent = $this->postJson(route('pos.kitchen.send'), $this->payload)->assertSuccessful()->json();
    $this->orderId = $this->sent['kitchen_order_id'];
});

it('delivers a cashier stop request to the PIN display and publishes confirmation to POS', function (): void {
    $stock = DB::table('products')->where('id', $this->payload['items'][0]['id'])->value('stock_quantity');
    $this->postJson(route('kitchen.status', $this->orderId), ['status' => 'preparing', 'revision' => 1])->assertSuccessful();
    $this->postJson(route('pos.kitchen.stop', $this->orderId), ['revision' => 1, 'reason' => 'Customer changed their mind'])->assertSuccessful();
    $this->assertDatabaseHas('kitchen_orders', ['id' => $this->orderId, 'status' => 'stop_requested', 'stop_requested_by' => $this->admin->id]);
    $this->post(route('logout'))->assertRedirect();
    $this->postJson(route('kod.unlock'), ['pin' => '0000'])->assertSuccessful();
    $this->getJson(route('kod.feed'))->assertSuccessful()->assertJsonPath('orders.0.status', 'stop_requested')
        ->assertJsonPath('orders.0.stop_reason', 'Customer changed their mind')->assertJsonPath('orders.0.stop_requester', $this->admin->name)
        ->assertJsonPath('orders.0.delete_url', route('kod.delete', $this->orderId))->assertJsonMissingPath('orders.0.stop_url');
    $this->postJson(route('kod.status', $this->orderId), ['status' => 'ready', 'revision' => 1])->assertUnprocessable();
    $this->postJson(route('kod.status', $this->orderId), ['status' => 'stopped', 'revision' => 1])->assertSuccessful();
    $this->actingAs($this->admin)->getJson(route('kitchen.feed'))->assertJsonPath('orders.0.status', 'stopped');
    $this->assertDatabaseHas('hold_orders', ['id' => $this->sent['hold_id'], 'status' => 'hold', 'deleted_at' => null]);
    expect(DB::table('products')->where('id', $this->payload['items'][0]['id'])->value('stock_quantity'))->toBe($stock)
        ->and(DB::table('sales')->count())->toBe(0)
        ->and(DB::table('kitchen_orders')->where('id', $this->orderId)->value('stopped_at'))->not->toBeNull();
});

it('lets kitchen staff stop directly and delete only the stopped kitchen ticket', function (string $initial): void {
    if ($initial === 'preparing') {
        $this->postJson(route('kitchen.status', $this->orderId), ['status' => 'preparing', 'revision' => 1])->assertSuccessful();
    }
    $this->postJson(route('kod.unlock'), ['pin' => '0000'])->assertSuccessful();
    $this->postJson(route('kod.status', $this->orderId), ['status' => 'stopped', 'revision' => 1])->assertSuccessful();
    $this->deleteJson(route('kod.delete', $this->orderId), ['revision' => 1])->assertSuccessful();
    $this->getJson(route('kod.feed'))->assertJsonCount(0, 'orders');
    $this->getJson(route('kitchen.feed'))->assertJsonCount(0, 'orders');
    $this->assertDatabaseHas('kitchen_orders', ['id' => $this->orderId, 'status' => 'stopped']);
    expect(DB::table('kitchen_orders')->where('id', $this->orderId)->value('deleted_at'))->not->toBeNull();
    $this->getJson(route('pos.resume-held-order', $this->sent['hold_id']))->assertSuccessful()->assertJsonCount(1, 'items');
    $this->assertDatabaseHas('restaurant_tables', ['id' => $this->payload['table_id'], 'status' => 'hold']);
    $this->postJson(route('kod.status', $this->orderId), ['status' => 'ready', 'revision' => 1])->assertNotFound();
    $this->deleteJson(route('kod.delete', $this->orderId), ['revision' => 1])->assertNotFound();
    $this->postJson(route('pos.kitchen.send'), [...$this->payload, 'hold_id' => $this->sent['hold_id']])->assertUnprocessable()->assertJsonValidationErrors('order');
    $this->assertDatabaseCount('kitchen_orders', 1);
})->with(['queued', 'preparing']);

it('rejects deletion before preparation is stopped', function (string $status): void {
    if ($status === 'preparing' || $status === 'ready') {
        $this->postJson(route('kitchen.status', $this->orderId), ['status' => $status, 'revision' => 1])->assertSuccessful();
    } elseif ($status === 'stop_requested') {
        $this->postJson(route('pos.kitchen.stop', $this->orderId), ['revision' => 1])->assertSuccessful();
    }
    $this->deleteJson(route('kitchen.delete', $this->orderId), ['revision' => 1])->assertUnprocessable()->assertJsonValidationErrors('status');
    $this->assertDatabaseHas('kitchen_orders', ['id' => $this->orderId, 'status' => $status, 'deleted_at' => null]);
})->with(['queued', 'preparing', 'ready', 'stop_requested']);

it('makes duplicate requests idempotent and prevents resending or finishing a pending stop', function (): void {
    $this->postJson(route('pos.kitchen.stop', $this->orderId), ['revision' => 1, 'reason' => 'First reason'])->assertSuccessful();
    $this->postJson(route('pos.kitchen.stop', $this->orderId), ['revision' => 1, 'reason' => 'Duplicate reason'])->assertSuccessful();
    $this->assertDatabaseHas('kitchen_orders', ['id' => $this->orderId, 'stop_reason' => 'First reason']);
    expect(DB::table('activity_logs')->where('module', 'kitchen')->where('action', 'stop_request')->count())->toBe(1);
    foreach (['preparing', 'ready', 'cancelled'] as $status) {
        $this->postJson(route('kitchen.status', $this->orderId), ['status' => $status, 'revision' => 1])->assertUnprocessable();
    }
    $this->postJson(route('pos.kitchen.send'), [...$this->payload, 'hold_id' => $this->sent['hold_id']])->assertUnprocessable();
    $this->assertDatabaseHas('hold_orders', ['id' => $this->sent['hold_id'], 'status' => 'hold', 'deleted_at' => null]);
});

it('rejects stale stop requests acknowledgements and deletions', function (): void {
    $this->postJson(route('pos.kitchen.stop', $this->orderId), ['revision' => 2])->assertUnprocessable()->assertJsonValidationErrors('revision');
    $this->postJson(route('pos.kitchen.stop', $this->orderId), ['revision' => 1])->assertSuccessful();
    $this->postJson(route('kitchen.status', $this->orderId), ['status' => 'stopped', 'revision' => 2])->assertUnprocessable()->assertJsonValidationErrors('revision');
    $this->postJson(route('kitchen.status', $this->orderId), ['status' => 'stopped', 'revision' => 1])->assertSuccessful();
    $this->deleteJson(route('kitchen.delete', $this->orderId), ['revision' => 2])->assertUnprocessable()->assertJsonValidationErrors('revision');
    $this->deleteJson(route('kitchen.delete', $this->orderId), ['revision' => 1])->assertSuccessful();
});

it('rejects stop requests after readiness or serving has won the race', function (string $status): void {
    $this->postJson(route('kitchen.status', $this->orderId), ['status' => 'ready', 'revision' => 1])->assertSuccessful();
    if ($status === 'served') {
        $this->postJson(route('pos.kitchen.served', $this->orderId), ['status' => 'served', 'revision' => 1])->assertSuccessful();
    }
    $this->postJson(route('pos.kitchen.stop', $this->orderId), ['revision' => 1])->assertUnprocessable()->assertJsonValidationErrors('status');
    $this->postJson(route('kitchen.status', $this->orderId), ['status' => 'stopped', 'revision' => 1])->assertUnprocessable();
    $this->assertDatabaseHas('kitchen_orders', ['id' => $this->orderId, 'status' => $status]);
})->with(['ready', 'served']);

it('keeps a cancelled held bills stop alert and original table until kitchen acknowledges', function (): void {
    $this->deleteJson(route('pos.hold.cancel', $this->sent['hold_id']))->assertSuccessful();
    $this->getJson(route('kitchen.feed'))->assertSuccessful()->assertJsonPath('orders.0.status', 'stop_requested')
        ->assertJsonPath('orders.0.table', DB::table('restaurant_tables')->where('id', $this->payload['table_id'])->value('number'))
        ->assertJsonPath('orders.0.resume_url', null)->assertJsonPath('orders.0.hold_id', null);
    $this->assertDatabaseHas('restaurant_tables', ['id' => $this->payload['table_id'], 'status' => 'available']);
    $this->postJson(route('kitchen.status', $this->orderId), ['status' => 'stopped', 'revision' => 1])->assertSuccessful();
    $this->deleteJson(route('kitchen.delete', $this->orderId), ['revision' => 1])->assertSuccessful();
});

it('retains an existing stop request when the cashier cancels its held bill', function (): void {
    $this->postJson(route('pos.kitchen.stop', $this->orderId), ['revision' => 1, 'reason' => 'Allergy'])->assertSuccessful();
    $this->deleteJson(route('pos.hold.cancel', $this->sent['hold_id']))->assertSuccessful();
    $this->getJson(route('kitchen.feed'))->assertJsonPath('orders.0.status', 'stop_requested')->assertJsonPath('orders.0.stop_reason', 'Allergy');
});

it('requires cashier permission for requests and kitchen permission for deletion', function (): void {
    $staff = User::factory()->create();
    $staff->roles()->attach(Role::query()->where('name', 'Kitchen Staff')->firstOrFail());
    $this->actingAs($staff)->postJson(route('pos.kitchen.stop', $this->orderId), ['revision' => 1])->assertForbidden();
    $this->postJson(route('kitchen.status', $this->orderId), ['status' => 'stopped', 'revision' => 1])->assertSuccessful();
    $this->actingAs(User::factory()->create())->deleteJson(route('kitchen.delete', $this->orderId), ['revision' => 1])->assertForbidden();
    $this->actingAs($staff)->deleteJson(route('kitchen.delete', $this->orderId), ['revision' => 1])->assertSuccessful();
});

it('protects PIN actions and never grants POS access to a kitchen guest', function (): void {
    $this->post(route('logout'))->assertRedirect();
    $this->deleteJson(route('kod.delete', $this->orderId), ['revision' => 1])->assertUnauthorized();
    $this->postJson(route('kod.status', $this->orderId), ['status' => 'stopped', 'revision' => 1])->assertUnauthorized();
    $this->postJson(route('kod.unlock'), ['pin' => '0000'])->assertSuccessful();
    $this->postJson(route('pos.kitchen.stop', $this->orderId), ['revision' => 1])->assertUnauthorized();
    $this->postJson(route('kod.status', $this->orderId), ['status' => 'stop_requested', 'revision' => 1])->assertUnprocessable();
    $this->postJson(route('kod.status', $this->orderId), ['status' => 'stopped', 'revision' => 1])->assertSuccessful();
    $this->deleteJson(route('kod.delete', $this->orderId), ['revision' => 1])->assertSuccessful();
    $this->assertGuest();
});

it('validates request reason revision and missing tickets', function (): void {
    $this->postJson(route('pos.kitchen.stop', $this->orderId), ['reason' => str_repeat('x', 501)])->assertUnprocessable()->assertJsonValidationErrors(['revision', 'reason']);
    $this->postJson(route('pos.kitchen.stop', 999999), ['revision' => 1])->assertNotFound();
    $this->deleteJson(route('kitchen.delete', 999999), ['revision' => 1])->assertNotFound();
});
