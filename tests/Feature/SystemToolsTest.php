<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;

function systemToolsUser(): User
{
    $permission = Permission::query()->firstOrCreate([
        'name' => 'system_tools.view',
    ], [
        'label' => 'System tools view',
        'category' => 'system_tools',
    ]);

    $role = Role::query()->create([
        'name' => 'Developer',
        'description' => 'Developer access',
        'is_active' => true,
    ]);

    $role->permissions()->attach($permission);

    /** @var User $user */
    $user = User::factory()->create([
        'is_active' => true,
    ]);

    $user->roles()->attach($role);

    return $user;
}

it('renders the system tools dashboard for authorized users', function () {
    $user = systemToolsUser();

    actingAs($user)
        ->get(route('system-tools.index'))
        ->assertSuccessful()
        ->assertSee('System Maintenance Controls')
        ->assertSee('Artisan Command Presets')
        ->assertSee('System Upgrade');
});

it('shows the system tools link to developer users only', function () {
    $developer = systemToolsUser();

    actingAs($developer)
        ->get(route('system-tools.index'))
        ->assertSuccessful()
        ->assertSee('Developer Tools');

    $permission = Permission::query()->firstOrCreate([
        'name' => 'dashboard.view',
    ], [
        'label' => 'Dashboard view',
        'category' => 'dashboard',
    ]);

    $role = Role::query()->create([
        'name' => 'Cashier',
        'description' => 'Cashier access',
        'is_active' => true,
    ]);

    $role->permissions()->attach($permission);

    /** @var User $user */
    $user = User::factory()->create([
        'is_active' => true,
    ]);

    $user->roles()->attach($role);

    actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertDontSee('Developer Tools');
});

it('does not expose the system tools link to non-developer roles', function () {
    $permission = Permission::query()->firstOrCreate([
        'name' => 'dashboard.view',
    ], [
        'label' => 'Dashboard view',
        'category' => 'dashboard',
    ]);

    $role = Role::query()->create([
        'name' => 'Cashier',
        'description' => 'Cashier access',
        'is_active' => true,
    ]);

    $role->permissions()->attach($permission);

    /** @var User $user */
    $user = User::factory()->create([
        'is_active' => true,
    ]);

    $user->roles()->attach($role);

    actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertDontSee('Developer Tools');
});

it('toggles the custom maintenance lock and blocks ordinary routes', function () {
    $user = systemToolsUser();

    actingAs($user)
        ->post(route('system-tools.maintenance'), ['enabled' => true])
        ->assertRedirect(route('system-tools.index'));

    expect(DB::table('settings')->where('group', 'system_tools')->where('key', 'maintenance_enabled')->value('value'))->toBe('1');

    /** @var User $plainUser */
    $plainUser = User::factory()->create([
        'is_active' => true,
    ]);

    actingAs($plainUser)
        ->get(route('dashboard'))
        ->assertForbidden();
});

it('runs an artisan command without using the process layer', function () {
    $user = systemToolsUser();

    actingAs($user)
        ->post(route('system-tools.commands.run'), [
            'command' => 'about',
            'command_label' => 'About',
        ])
        ->assertRedirect(route('system-tools.index'));

    $result = DB::table('settings')->where('group', 'system_tools')->where('key', 'last_execution')->value('value');

    expect($result)->not->toBeEmpty();
    expect($result)->toContain('About');
});
