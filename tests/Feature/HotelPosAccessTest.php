<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

it('authenticates active users by username', function () {
    $role = Role::query()->create(['name' => 'Cashier', 'is_active' => true]);
    $permission = Permission::query()->create(['name' => 'dashboard.view', 'label' => 'Dashboard view', 'category' => 'dashboard']);
    $role->permissions()->attach($permission);

    $user = User::factory()->create(['username' => 'cashier']);
    $user->roles()->attach($role);

    $this->post(route('login.store'), [
        'username' => 'cashier',
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('blocks pages when a permission is missing', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('pos.index'))->assertForbidden();
});

it('renders seeded dashboard and pos screens for the super admin', function () {
    $this->seed();
    $admin = User::query()->where('username', 'admin')->firstOrFail();

    $this->actingAs($admin)->get(route('dashboard'))->assertSuccessful()->assertSee('Total Sales');
    $this->actingAs($admin)->get(route('pos.index'))->assertSuccessful()->assertSee('Dining room', false);
    $this->actingAs($admin)->get(route('reports.show', 'sales'))->assertSuccessful()->assertSee('Sales Report');
});
