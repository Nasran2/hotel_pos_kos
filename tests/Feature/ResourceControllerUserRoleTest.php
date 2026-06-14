<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);

    $this->admin = User::query()->where('username', 'admin')->firstOrFail();
    $this->actingAs($this->admin);
});

it('preselects the current role when editing a user', function (): void {
    $existingRole = Role::query()->create([
        'name' => 'Cashier',
        'description' => 'Cashier role',
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'name' => 'Role Editor',
        'username' => 'role-editor',
    ]);
    $user->roles()->sync([$existingRole->id]);

    $this->get(route('backoffice.modules.edit', ['users', $user->id]))
        ->assertSuccessful()
        ->assertSeeHtml('value="'.$existingRole->id.'" selected');
});

it('updates the pivot role when saving a user', function (): void {
    $currentRole = Role::query()->create([
        'name' => 'Server',
        'description' => 'Server role',
        'is_active' => true,
    ]);

    $newRole = Role::query()->create([
        'name' => 'Supervisor',
        'description' => 'Supervisor role',
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'name' => 'Role Saver',
        'username' => 'role-saver',
    ]);
    $user->roles()->sync([$currentRole->id]);

    $this->put(route('backoffice.modules.update', ['users', $user->id]), [
        'name' => 'Role Saver',
        'username' => 'role-saver',
        'phone' => $user->phone,
        'address' => $user->address,
        'email' => $user->email,
        'password' => '',
        'password_confirmation' => '',
        'role_id' => $newRole->id,
        'is_active' => 1,
    ])->assertRedirect(route('backoffice.modules.show', ['users', $user->id]));

    $this->assertDatabaseHas('user_roles', [
        'user_id' => $user->id,
        'role_id' => $newRole->id,
    ]);

    $this->assertDatabaseMissing('user_roles', [
        'user_id' => $user->id,
        'role_id' => $currentRole->id,
    ]);
});
