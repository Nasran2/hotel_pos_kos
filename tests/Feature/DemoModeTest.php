<?php

use App\Models\Role;
use App\Models\User;
use App\Services\DemoMode;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schedule;

beforeEach(function (): void {
    config(['demo.enabled' => false]);
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::query()->where('username', 'admin')->firstOrFail();
    $this->actingAs($this->admin);
});

it('shows a small demo banner and locks business identity fields only in demo mode', function (): void {
    $this->get(route('settings.edit'))->assertDontSee('All data will reset every 15 days.');
    config(['demo.enabled' => true]);
    $this->get(route('settings.edit'))->assertSuccessful()->assertSee('All data will reset every 15 days.')
        ->assertSee('Protected in demo mode');
    $this->get(route('pos.index'))->assertSuccessful()->assertSee('This is a demo.');
    $this->get(route('kod.index'))->assertSuccessful()->assertSee('This is a demo.');
    $this->get(route('login'))->assertRedirect();
    $this->post(route('logout'))->assertRedirect();
    $this->get(route('login'))->assertSuccessful()->assertSee('All data will reset every 15 days.');
});

it('rejects crafted business identity changes and allows other settings in demo', function (): void {
    config(['demo.enabled' => true]);
    $this->putJson(route('settings.update'), ['section' => 'pos', 'settings' => ['business' => ['name' => 'Changed']]])
        ->assertUnprocessable()->assertJsonValidationErrors('settings.business.name');
    $this->putJson(route('settings.update'), ['settings' => ['business' => ['tagline' => 'Changed']]])->assertUnprocessable();
    $this->putJson(route('settings.update'), ['settings' => ['business' => ['name' => null]]])->assertUnprocessable();
    $this->putJson(route('settings.update'), ['files' => ['business' => ['name' => UploadedFile::fake()->image('name.png')]]])->assertUnprocessable();
    $this->putJson(route('settings.update'), ['settings' => ['system_tools' => ['maintenance_enabled' => '1']]])->assertUnprocessable();
    $this->put(route('settings.update'), ['settings' => ['business' => ['name' => 'Hotel POS', 'phone' => '0112345678'], 'pos' => ['service_charge_percentage' => '5']]])->assertRedirect();
    $this->assertDatabaseHas('settings', ['group' => 'business', 'key' => 'name', 'value' => 'Hotel POS']);
    $this->assertDatabaseHas('settings', ['group' => 'business', 'key' => 'phone', 'value' => '0112345678']);
});

it('protects admin credentials deletion deactivation and role access in demo', function (): void {
    config(['demo.enabled' => true]);
    $hash = $this->admin->password;
    $this->get(route('backoffice.modules.edit', ['users', $this->admin->id]))->assertSuccessful()->assertSee('protected in demo mode');
    $this->putJson(route('backoffice.modules.update', ['users', $this->admin->id]), ['password' => 'changed123', 'password_confirmation' => 'changed123'])->assertForbidden();
    $this->deleteJson(route('backoffice.modules.destroy', ['users', $this->admin->id]))->assertForbidden();
    $this->postJson(route('backoffice.modules.toggle-active', ['users', $this->admin->id]), ['is_active' => false])->assertForbidden();
    $role = Role::query()->where('name', 'Super Admin')->firstOrFail();
    $this->putJson(route('backoffice.modules.update', ['roles', $role->id]), ['name' => 'Other'])->assertForbidden();
    $this->deleteJson(route('backoffice.modules.destroy', ['roles', $role->id]))->assertForbidden();
    expect($this->admin->fresh()->password)->toBe($hash)->and($this->admin->fresh()->is_active)->toBeTrue();
});

it('allows business names and admin passwords to change in live mode', function (): void {
    $this->put(route('settings.update'), ['settings' => ['business' => ['name' => 'Live Hotel', 'tagline' => 'Live hospitality']]])->assertRedirect();
    $this->put(route('backoffice.modules.update', ['users', $this->admin->id]), [
        'name' => $this->admin->name, 'username' => 'admin', 'email' => $this->admin->email,
        'password' => 'newpassword123', 'password_confirmation' => 'newpassword123',
        'role_id' => Role::query()->where('name', 'Super Admin')->value('id'), 'is_active' => true,
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect(Hash::check('newpassword123', $this->admin->fresh()->password))->toBeTrue();
    $this->assertDatabaseHas('settings', ['group' => 'business', 'key' => 'name', 'value' => 'Live Hotel']);
});

it('does nothing when live even if a manual reset command is overdue', function (): void {
    DB::table('demo_reset_cycles')->insert(['id' => 1, 'next_reset_at' => now()->subDays(30), 'created_at' => now(), 'updated_at' => now()]);
    $productCount = DB::table('products')->count();
    $hash = $this->admin->password;
    $this->artisan('demo:reset')->expectsOutput('Demo mode is disabled. No data changed.')->assertSuccessful();
    expect(DB::table('products')->count())->toBe($productCount)->and($this->admin->fresh()->password)->toBe($hash);
    expect(DB::table('demo_reset_cycles')->where('id', 1)->value('last_reset_at'))->toBeNull();
});

it('starts a cycle without resetting then resets exactly once at the fifteen day boundary', function (): void {
    config(['demo.enabled' => true]);
    $this->freezeTime();
    $this->artisan('demo:reset')->expectsOutput('Demo reset cycle started. First reset is due in 15 days.')->assertSuccessful();
    $customer = User::factory()->create();
    $product = DB::table('products')->first();
    $sent = $this->postJson(route('pos.kitchen.send'), [
        'customer_id' => DB::table('customers')->where('is_walk_in', true)->value('id'),
        'items' => [['id' => $product->id, 'name' => $product->name, 'price' => 100, 'quantity' => 1]],
    ])->assertSuccessful();
    DB::table('products')->where('id', $product->id)->update(['stock_quantity' => 1]);
    $this->travel(15 * 24 * 60 * 60 - 1)->seconds();
    $this->artisan('demo:reset')->expectsOutput('Demo reset is not due. No data changed.')->assertSuccessful();
    $this->assertDatabaseHas('kitchen_orders', ['id' => $sent->json('kitchen_order_id')]);
    $this->travel(1)->seconds();
    $this->artisan('demo:reset')->expectsOutput('Demo data reset successfully. Next reset is due in 15 days.')->assertSuccessful();
    foreach (['kitchen_orders', 'hold_orders', 'hold_order_items', 'order_tokens', 'daily_token_counters', 'sales', 'activity_logs'] as $table) {
        $this->assertDatabaseCount($table, 0);
    }
    $this->assertDatabaseMissing('users', ['id' => $customer->id]);
    $this->assertDatabaseHas('users', ['id' => $this->admin->id, 'username' => 'admin', 'is_active' => true]);
    $this->assertDatabaseHas('settings', ['group' => 'business', 'key' => 'name', 'value' => 'Hotel POS']);
    expect(DB::table('products')->where('name', $product->name)->value('stock_quantity'))->not->toBe(1);
    expect(Role::query()->where('name', 'Kitchen Staff')->firstOrFail()->permissions()->count())->toBe(2);
    expect(DB::table('demo_reset_cycles')->where('id', 1)->value('last_reset_at'))->not->toBeNull();
    $this->artisan('demo:reset')->expectsOutput('Demo reset is not due. No data changed.')->assertSuccessful();
    $this->assertDatabaseCount('products', 8);
});

it('rolls back data and reset timing if seed restoration fails', function (): void {
    config(['demo.enabled' => true]);
    DB::table('demo_reset_cycles')->insert(['id' => 1, 'next_reset_at' => now()->subSecond(), 'created_at' => now(), 'updated_at' => now()]);
    $this->mock(DatabaseSeeder::class)->shouldReceive('run')->once()->andThrow(new RuntimeException('Seed failed'));
    expect(fn () => app(DemoMode::class)->resetIfDue())->toThrow(RuntimeException::class, 'Seed failed');
    $this->assertDatabaseCount('products', 8);
    expect(DB::table('demo_reset_cycles')->where('id', 1)->value('last_reset_at'))->toBeNull();
    $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
});

it('blocks mutation tools and public database repair in demo regardless of developer role', function (): void {
    config(['demo.enabled' => true]);
    $role = Role::query()->create(['name' => 'Developer', 'is_active' => true]);
    $this->admin->roles()->attach($role);
    $this->postJson(route('system-tools.commands.run'), ['command' => 'db:seed --force'])->assertForbidden();
    $this->get('/fix-db')->assertForbidden();
});

it('registers a guarded scheduler task for demo resets', function (): void {
    $event = collect(Schedule::events())->first(fn ($event): bool => str_contains($event->command ?? '', 'demo:reset'));
    expect($event)->not->toBeNull()->and($event->expression)->toBe('* * * * *')->and($event->withoutOverlapping)->toBeTrue();
    expect($event->filtersPass(app()))->toBeFalse();
    config(['demo.enabled' => true]);
    expect($event->filtersPass(app()))->toBeTrue();
});

it('keeps other demo profiles editable without exposing password hashes', function (): void {
    config(['demo.enabled' => true]);
    $staff = User::factory()->create();
    $this->get(route('backoffice.modules.index', 'users'))->assertSuccessful()->assertDontSee(substr($this->admin->password, 0, 30));
    $this->postJson(route('backoffice.modules.toggle-active', ['users', $staff->id]), ['is_active' => '0'])
        ->assertSuccessful()->assertJsonMissingPath('record.password')->assertJsonMissingPath('record.remember_token');
    expect($staff->fresh()->is_active)->toBeFalse();
});
