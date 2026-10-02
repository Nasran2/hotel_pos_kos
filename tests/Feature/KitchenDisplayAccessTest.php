<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::query()->where('username', 'admin')->firstOrFail();
});

it('opens a standalone PIN screen without a user login and redirects the old kitchen page', function (): void {
    $this->get(route('kod.index'))->assertSuccessful()->assertSee('Four-digit PIN')
        ->assertSee('data-kod-unlocked="0"', false)->assertSee('data-kod-board hidden inert', false)
        ->assertDontSee('data-sidebar', false)->assertHeader('Cache-Control', 'no-store, private');
    $this->get(route('kitchen.index'))->assertRedirect(route('kod.index'));
    $this->getJson(route('kod.feed'))->assertUnauthorized()->assertJsonMissingPath('orders');
    $this->postJson(route('kod.status', 1), ['status' => 'ready', 'revision' => 1])->assertUnauthorized();
    $this->assertGuest();
});

it('accepts the default 0000 PIN without giving POS or settings access', function (): void {
    $this->postJson(route('kod.unlock'), ['pin' => '0000'])->assertSuccessful()->assertJsonStructure(['csrf_token']);
    $this->get(route('kod.index'))->assertSee('data-kod-unlocked="1"', false);
    $this->getJson(route('kod.feed'))->assertSuccessful()->assertJsonCount(0, 'orders');
    $this->getJson(route('pos.index'))->assertUnauthorized();
    $this->putJson(route('settings.kitchen-pin'), ['pin' => '1234', 'pin_confirmation' => '1234'])->assertUnauthorized();
    $this->assertGuest();
});

it('rejects incorrect PINs without granting access or flashing them', function (): void {
    $this->from(route('kod.index'))->post(route('kod.unlock'), ['pin' => '9876'])
        ->assertRedirect(route('kod.index'))->assertSessionHasErrors('pin')->assertSessionMissing('_old_input.pin');
    $this->getJson(route('kod.feed'))->assertUnauthorized();
});

it('requires exactly four ASCII digits', function (mixed $pin): void {
    $this->postJson(route('kod.unlock'), ['pin' => $pin])->assertUnprocessable()->assertJsonValidationErrors('pin');
    $this->getJson(route('kod.feed'))->assertUnauthorized();
})->with(['short' => '000', 'long' => '00000', 'letters' => '12a4', 'numeric value' => 1234, 'unicode digits' => '１２３４']);

it('limits PIN attempts and permits another attempt after the wait', function (): void {
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson(route('kod.unlock'), ['pin' => '9999'])->assertUnprocessable();
    }
    $this->postJson(route('kod.unlock'), ['pin' => '0000'])->assertTooManyRequests()->assertHeader('Retry-After');
    $this->travel(61)->seconds();
    $this->postJson(route('kod.unlock'), ['pin' => '0000'])->assertSuccessful();
});

it('persists a hashed PIN including leading zeroes and revokes previous display access', function (): void {
    $this->postJson(route('kod.unlock'), ['pin' => '0000'])->assertSuccessful();
    $this->actingAs($this->admin)->put(route('settings.kitchen-pin'), ['pin' => '0042', 'pin_confirmation' => '0042'])
        ->assertRedirect(route('settings.edit', ['section' => 'kitchen']));
    $hash = DB::table('settings')->where('group', 'kitchen')->where('key', 'pin_hash')->value('value');
    expect($hash)->not->toBe('0042')->and(Hash::check('0042', $hash))->toBeTrue();
    $this->getJson(route('kod.feed'))->assertUnauthorized();
    $this->get(route('settings.edit', ['section' => 'kitchen']))->assertSuccessful()->assertSee('New four-digit PIN')
        ->assertDontSee($hash)->assertDontSee('value="0042"', false);
    $this->postJson(route('kod.unlock'), ['pin' => '0000'])->assertUnprocessable();
    $this->postJson(route('kod.unlock'), ['pin' => '0042'])->assertSuccessful();
    $this->getJson(route('kod.feed'))->assertSuccessful();
    expect(DB::table('activity_logs')->where('module', 'settings')->first()->properties ?? '')->not->toContain('0042');
});

it('protects PIN updates with settings permissions and confirmation', function (): void {
    $staff = User::factory()->create();
    $staff->roles()->attach(Role::query()->where('name', 'Kitchen Staff')->firstOrFail());
    $this->actingAs($staff)->putJson(route('settings.kitchen-pin'), ['pin' => '1234', 'pin_confirmation' => '1234'])->assertForbidden();
    $this->actingAs($this->admin)->from(route('settings.edit', ['section' => 'kitchen']))
        ->put(route('settings.kitchen-pin'), ['pin' => '1234', 'pin_confirmation' => '5678'])
        ->assertSessionHasErrors('pin')->assertSessionMissing('_old_input.pin')->assertSessionMissing('_old_input.pin_confirmation');
    $this->putJson(route('settings.kitchen-pin'), ['pin' => '123', 'pin_confirmation' => '123'])->assertUnprocessable();
    $this->putJson(route('settings.kitchen-pin'), ['pin' => '', 'pin_confirmation' => ''])->assertUnprocessable();
    expect(DB::table('settings')->where('group', 'kitchen')->exists())->toBeFalse();
});

it('prevents generic settings and file uploads from overwriting the PIN hash', function (): void {
    $this->actingAs($this->admin)->put(route('settings.kitchen-pin'), ['pin' => '1357', 'pin_confirmation' => '1357'])->assertRedirect();
    $hash = DB::table('settings')->where('group', 'kitchen')->where('key', 'pin_hash')->value('value');
    $this->put(route('settings.update'), ['settings' => ['kitchen' => ['pin_hash' => '0000'], 'business' => ['name' => 'Updated Hotel']]])->assertRedirect();
    expect(DB::table('settings')->where('group', 'kitchen')->where('key', 'pin_hash')->value('value'))->toBe($hash);
    $this->assertDatabaseHas('settings', ['group' => 'business', 'key' => 'name', 'value' => 'Updated Hotel']);
});

it('allows PIN-only kitchen transitions and publishes ready state to POS', function (): void {
    $product = DB::table('products')->first();
    $sent = $this->actingAs($this->admin)->postJson(route('pos.kitchen.send'), [
        'customer_id' => DB::table('customers')->where('is_walk_in', true)->value('id'),
        'items' => [['id' => $product->id, 'name' => $product->name, 'quantity' => 1, 'price' => 100, 'note' => 'No chilli']],
    ])->assertSuccessful();
    $id = $sent->json('kitchen_order_id');
    $this->post(route('logout'))->assertRedirect();
    $this->postJson(route('kod.unlock'), ['pin' => '0000'])->assertSuccessful();
    $this->getJson(route('kod.feed'))->assertSuccessful()->assertJsonPath('orders.0.status', 'queued')
        ->assertJsonPath('orders.0.items.0.note', 'No chilli')->assertJsonPath('orders.0.status_url', route('kod.status', $id))
        ->assertJsonMissingPath('orders.0.resume_url')->assertJsonMissingPath('orders.0.served_url')->assertJsonMissingPath('orders.0.hold_id');
    $this->postJson(route('kod.status', $id), ['status' => 'served', 'revision' => 1])->assertUnprocessable();
    $this->postJson(route('kod.status', $id), ['status' => 'cancelled', 'revision' => 1])->assertUnprocessable();
    $this->postJson(route('kod.status', $id), ['status' => 'preparing', 'revision' => 1])->assertSuccessful();
    $this->postJson(route('kod.status', $id), ['status' => 'ready', 'revision' => 9])->assertUnprocessable()->assertJsonValidationErrors('revision');
    $this->postJson(route('kod.status', $id), ['status' => 'ready', 'revision' => 1])->assertSuccessful();
    $this->postJson(route('pos.kitchen.served', $id), ['status' => 'served', 'revision' => 1])->assertUnauthorized();
    $this->assertGuest();
    $this->actingAs($this->admin)->getJson(route('kitchen.feed'))->assertSuccessful()->assertJsonPath('orders.0.status', 'ready');
});

it('locks the kitchen without logging out the POS user', function (): void {
    $this->actingAs($this->admin)->get(route('pos.index'))->assertSuccessful();
    $csrfToken = session()->token();
    $this->postJson(route('kod.unlock'), ['pin' => '0000'])->assertSuccessful()->assertJsonPath('csrf_token', $csrfToken);
    $this->postJson(route('kod.lock'))->assertSuccessful();
    $this->getJson(route('kod.feed'))->assertUnauthorized();
    $this->postJson(route('kod.status', 1), ['status' => 'ready', 'revision' => 1])->assertUnauthorized();
    $this->get(route('kod.index'))->assertSee('data-kod-unlocked="0"', false);
    $this->assertAuthenticatedAs($this->admin);
});


it('keeps an already authenticated cashier session stable while unlocking kitchen', function (): void {
    $this->actingAs($this->admin)->get(route('pos.index'))->assertSuccessful();
    $sessionId = session()->getId();
    $this->withCredentials()->withCookie(config('session.cookie'), $sessionId);
    $csrfToken = session()->token();
    $this->postJson(route('kod.unlock'), ['pin' => '0000'])->assertSuccessful()->assertJsonPath('csrf_token', $csrfToken);
    expect(session()->getId())->toBe($sessionId);
    $this->getJson(route('kitchen.feed'))->assertSuccessful();
    $this->getJson(route('kod.feed'))->assertSuccessful();
    $this->assertAuthenticatedAs($this->admin);
});

it('rotates a guest session when granting PIN access', function (): void {
    $this->withSession(['marker' => 'guest'])->get(route('kod.index'))->assertSuccessful();
    $sessionId = session()->getId();
    $this->withCredentials()->withCookie(config('session.cookie'), $sessionId);
    $this->postJson(route('kod.unlock'), ['pin' => '0000'])->assertSuccessful();
    expect(session()->getId())->not->toBe($sessionId);
    $this->getJson(route('kod.feed'))->assertSuccessful();
    $this->assertGuest();
});
