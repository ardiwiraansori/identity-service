<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_user_list(): void
    {
        $response = $this->getJson('/api/v1/users');

        $response
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_guest_cannot_view_user_detail(): void
    {
        $response = $this->getJson('/api/v1/users/1');

        $response
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_authenticated_user_without_permission_cannot_view_user_list(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/users');

        $response->assertForbidden();
    }

    public function test_authenticated_user_without_permission_cannot_view_user_detail(): void
    {
        $authenticatedUser = User::factory()->create();
        $managedUser = User::factory()->create();

        Sanctum::actingAs($authenticatedUser);

        $response = $this->getJson("/api/v1/users/{$managedUser->id}");

        $response->assertForbidden();
    }

    public function test_authenticated_user_with_permission_can_view_user_list(): void
    {
        $permission = Permission::query()->create([
            'name' => 'users.view',
            'guard_name' => 'web',
        ]);

        $administrator = User::factory()->create([
            'name' => 'Administrator',
            'email' => 'administrator@property.test',
        ]);

        $administrator->givePermissionTo($permission);

        $managedUser = User::factory()->create([
            'name' => 'Budi Developer',
            'email' => 'budi@property.test',
        ]);

        Sanctum::actingAs($administrator);

        $response = $this->getJson('/api/v1/users');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.id', $administrator->id)
            ->assertJsonPath('data.0.name', 'Administrator')
            ->assertJsonPath('data.0.email', 'administrator@property.test')
            ->assertJsonPath('data.0.is_active', true)
            ->assertJsonPath('data.1.id', $managedUser->id)
            ->assertJsonPath('data.1.name', 'Budi Developer')
            ->assertJsonPath('data.1.email', 'budi@property.test')
            ->assertJsonPath('data.1.is_active', true)
            ->assertJsonMissingPath('data.0.password')
            ->assertJsonMissingPath('data.1.password')
            ->assertJsonStructure([
                'data',
                'links',
                'meta',
            ]);
    }

    public function test_authenticated_user_with_permission_can_view_user_detail(): void
    {
        $permission = Permission::query()->create([
            'name' => 'users.view',
            'guard_name' => 'web',
        ]);

        $role = Role::query()->create([
            'name' => 'sales-admin',
            'guard_name' => 'web',
        ]);

        $administrator = User::factory()->create();
        $administrator->givePermissionTo($permission);

        $managedUser = User::factory()->create([
            'name' => 'Siti Sales',
            'email' => 'siti@property.test',
        ]);

        $managedUser->assignRole($role);

        Sanctum::actingAs($administrator);

        $response = $this->getJson("/api/v1/users/{$managedUser->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $managedUser->id)
            ->assertJsonPath('data.name', 'Siti Sales')
            ->assertJsonPath('data.email', 'siti@property.test')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.roles.0', 'sales-admin')
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token');
    }

    public function test_guest_cannot_create_user(): void
    {
        $response = $this->postJson('/api/v1/users', [
            'name' => 'Budi Sales',
            'email' => 'budi.sales@property.test',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
            'role' => 'super-admin',
        ]);

        $response
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);

        $this->assertDatabaseMissing('users', [
            'email' => 'budi.sales@property.test',
        ]);
    }

    public function test_authenticated_user_without_permission_cannot_create_user(): void
    {
        $authenticatedUser = User::factory()->create();

        Sanctum::actingAs($authenticatedUser);

        $response = $this->postJson('/api/v1/users', [
            'name' => 'Budi Sales',
            'email' => 'budi.sales@property.test',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
            'role' => 'super-admin',
        ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('users', [
            'email' => 'budi.sales@property.test',
        ]);
    }

    public function test_authenticated_user_with_permission_can_create_user(): void
    {
        $permission = Permission::query()->create([
            'name' => 'users.create',
            'guard_name' => 'web',
        ]);

        Role::query()->create([
            'name' => 'sales-admin',
            'guard_name' => 'web',
        ]);

        $administrator = User::factory()->create();
        $administrator->givePermissionTo($permission);

        Sanctum::actingAs($administrator);

        $response = $this->postJson('/api/v1/users', [
            'name' => 'Budi Sales',
            'email' => 'budi.sales@property.test',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
            'role' => 'sales-admin',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'User berhasil dibuat.')
            ->assertJsonPath('data.name', 'Budi Sales')
            ->assertJsonPath('data.email', 'budi.sales@property.test')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.roles.0', 'sales-admin')
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token');

        $createdUser = User::query()
            ->where('email', 'budi.sales@property.test')
            ->firstOrFail();

        $this->assertTrue(
            Hash::check('Secret123!', $createdUser->password),
        );

        $this->assertTrue($createdUser->hasRole('sales-admin'));
    }

    public function test_create_user_rejects_duplicate_email(): void
    {
        $permission = Permission::query()->create([
            'name' => 'users.create',
            'guard_name' => 'web',
        ]);

        Role::query()->create([
            'name' => 'sales-admin',
            'guard_name' => 'web',
        ]);

        User::factory()->create([
            'email' => 'budi.sales@property.test',
        ]);

        $administrator = User::factory()->create();
        $administrator->givePermissionTo($permission);

        Sanctum::actingAs($administrator);

        $response = $this->postJson('/api/v1/users', [
            'name' => 'Budi Sales Baru',
            'email' => 'budi.sales@property.test',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
            'role' => 'sales-admin',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email',
            ]);

        $this->assertDatabaseCount('users', 2);
    }

    public function test_create_user_rejects_invalid_role(): void
    {
        $permission = Permission::query()->create([
            'name' => 'users.create',
            'guard_name' => 'web',
        ]);

        $administrator = User::factory()->create();
        $administrator->givePermissionTo($permission);

        Sanctum::actingAs($administrator);

        $response = $this->postJson('/api/v1/users', [
            'name' => 'Budi Sales',
            'email' => 'budi.sales@property.test',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
            'role' => 'role-tidak-tersedia',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'role',
            ]);

        $this->assertDatabaseMissing('users', [
            'email' => 'budi.sales@property.test',
        ]);
    }

    public function test_create_user_rejects_mismatched_password_confirmation(): void
    {
        $permission = Permission::query()->create([
            'name' => 'users.create',
            'guard_name' => 'web',
        ]);

        Role::query()->create([
            'name' => 'sales-admin',
            'guard_name' => 'web',
        ]);

        $administrator = User::factory()->create();
        $administrator->givePermissionTo($permission);

        Sanctum::actingAs($administrator);

        $response = $this->postJson('/api/v1/users', [
            'name' => 'Budi Sales',
            'email' => 'budi.sales@property.test',
            'password' => 'Secret123!',
            'password_confirmation' => 'PasswordBerbeda123!',
            'role' => 'sales-admin',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'password',
            ]);

        $this->assertDatabaseMissing('users', [
            'email' => 'budi.sales@property.test',
        ]);
    }

    public function test_create_user_rejects_weak_password(): void
    {
        $permission = Permission::query()->create([
            'name' => 'users.create',
            'guard_name' => 'web',
        ]);

        Role::query()->create([
            'name' => 'sales-admin',
            'guard_name' => 'web',
        ]);

        $administrator = User::factory()->create();
        $administrator->givePermissionTo($permission);

        Sanctum::actingAs($administrator);

        $response = $this->postJson('/api/v1/users', [
            'name' => 'Budi Sales',
            'email' => 'budi.sales@property.test',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'sales-admin',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'password',
            ]);

        $this->assertDatabaseMissing('users', [
            'email' => 'budi.sales@property.test',
        ]);
    }

    public function test_guest_cannot_update_user(): void
    {
        $user = User::factory()->create();

        $originalName = $user->name;

        $response = $this->patchJson(
            "/api/v1/users/{$user->id}",
            [
                'name' => 'Updated User',
            ],
        );

        $response->assertUnauthorized();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => $originalName,
        ]);
    }

    public function test_authenticated_user_without_permission_cannot_update_user(): void
    {
        $authenticatedUser = User::factory()->create();
        $targetUser = User::factory()->create();

        $originalName = $targetUser->name;

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->patchJson(
                "/api/v1/users/{$targetUser->id}",
                [
                    'name' => 'Updated User',
                ],
            );

        $response->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'name' => $originalName,
        ]);
    }

    public function test_authenticated_user_with_permission_can_update_user_name(): void
    {
        $permission = Permission::findOrCreate('users.update', 'web');

        $authenticatedUser = User::factory()->create();
        $authenticatedUser->givePermissionTo($permission);

        $targetUser = User::factory()->create([
            'name' => 'Original User',
        ]);

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->patchJson(
                "/api/v1/users/{$targetUser->id}",
                [
                    'name' => 'Updated User',
                ],
            );

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $targetUser->id)
            ->assertJsonPath('data.name', 'Updated User');

        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'name' => 'Updated User',
        ]);
    }

    public function test_update_user_allows_existing_email_of_the_same_user(): void
    {
        $permission = Permission::findOrCreate('users.update', 'web');

        $authenticatedUser = User::factory()->create();
        $authenticatedUser->givePermissionTo($permission);

        $targetUser = User::factory()->create([
            'email' => 'target@example.com',
        ]);

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->patchJson(
                "/api/v1/users/{$targetUser->id}",
                [
                    'email' => 'target@example.com',
                ],
            );

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $targetUser->id)
            ->assertJsonPath('data.email', 'target@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'email' => 'target@example.com',
        ]);
    }

    public function test_update_user_rejects_email_used_by_another_user(): void
    {
        $permission = Permission::findOrCreate('users.update', 'web');

        $authenticatedUser = User::factory()->create();
        $authenticatedUser->givePermissionTo($permission);

        $targetUser = User::factory()->create([
            'email' => 'target@example.com',
        ]);

        User::factory()->create([
            'email' => 'used@example.com',
        ]);

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->patchJson(
                "/api/v1/users/{$targetUser->id}",
                [
                    'email' => 'used@example.com',
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'email' => 'target@example.com',
        ]);
    }

    public function test_authenticated_user_with_permission_can_update_user_email(): void
    {
        $permission = Permission::findOrCreate('users.update', 'web');

        $authenticatedUser = User::factory()->create();
        $authenticatedUser->givePermissionTo($permission);

        $targetUser = User::factory()->create([
            'email' => 'old@example.com',
        ]);

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->patchJson(
                "/api/v1/users/{$targetUser->id}",
                [
                    'email' => 'new@example.com',
                ],
            );

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $targetUser->id)
            ->assertJsonPath('data.email', 'new@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'email' => 'new@example.com',
        ]);

        $this->assertDatabaseMissing('users', [
            'id' => $targetUser->id,
            'email' => 'old@example.com',
        ]);
    }

    public function test_authenticated_user_with_permission_can_update_user_role(): void
    {
        $permission = Permission::findOrCreate('users.update', 'web');

        $authenticatedUser = User::factory()->create();
        $authenticatedUser->givePermissionTo($permission);

        $oldRole = Role::findOrCreate('old-role', 'web');
        $newRole = Role::findOrCreate('new-role', 'web');

        $targetUser = User::factory()->create();
        $targetUser->assignRole($oldRole);

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->patchJson(
                "/api/v1/users/{$targetUser->id}",
                [
                    'role' => $newRole->name,
                ],
            );

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $targetUser->id)
            ->assertJsonPath('data.roles.0', $newRole->name);

        $targetUser->refresh();

        $this->assertTrue($targetUser->hasRole($newRole));
        $this->assertFalse($targetUser->hasRole($oldRole));
    }

    public function test_update_user_rejects_invalid_role(): void
    {
        $permission = Permission::findOrCreate('users.update', 'web');

        $authenticatedUser = User::factory()->create();
        $authenticatedUser->givePermissionTo($permission);

        $originalRole = Role::findOrCreate('original-role', 'web');

        $targetUser = User::factory()->create();
        $targetUser->assignRole($originalRole);

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->patchJson(
                "/api/v1/users/{$targetUser->id}",
                [
                    'role' => 'role-does-not-exist',
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);

        $targetUser->refresh();

        $this->assertTrue($targetUser->hasRole($originalRole));
        $this->assertFalse($targetUser->hasRole('role-does-not-exist'));
    }

    public function test_authenticated_user_with_permission_can_update_user_password(): void
    {
        $permission = Permission::findOrCreate('users.update', 'web');

        $authenticatedUser = User::factory()->create();
        $authenticatedUser->givePermissionTo($permission);

        $targetUser = User::factory()->create([
            'password' => 'OldPassword123!',
        ]);

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->patchJson(
                "/api/v1/users/{$targetUser->id}",
                [
                    'password' => 'NewPassword123!',
                    'password_confirmation' => 'NewPassword123!',
                ],
            );

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $targetUser->id)
            ->assertJsonMissingPath('data.password');

        $targetUser->refresh();

        $this->assertTrue(
            Hash::check('NewPassword123!', $targetUser->password),
        );

        $this->assertFalse(
            Hash::check('OldPassword123!', $targetUser->password),
        );

        $this->assertNotSame(
            'NewPassword123!',
            $targetUser->password,
        );
    }

    public function test_update_user_rejects_mismatched_password_confirmation(): void
    {
        $permission = Permission::findOrCreate('users.update', 'web');

        $authenticatedUser = User::factory()->create();
        $authenticatedUser->givePermissionTo($permission);

        $targetUser = User::factory()->create([
            'password' => 'OldPassword123!',
        ]);

        $originalPasswordHash = $targetUser->password;

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->patchJson(
                "/api/v1/users/{$targetUser->id}",
                [
                    'password' => 'NewPassword123!',
                    'password_confirmation' => 'DifferentPassword123!',
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $targetUser->refresh();

        $this->assertSame(
            $originalPasswordHash,
            $targetUser->password,
        );

        $this->assertTrue(
            Hash::check('OldPassword123!', $targetUser->password),
        );

        $this->assertFalse(
            Hash::check('NewPassword123!', $targetUser->password),
        );
    }

    public function test_update_user_rejects_weak_password(): void
    {
        $permission = Permission::findOrCreate('users.update', 'web');

        $authenticatedUser = User::factory()->create();
        $authenticatedUser->givePermissionTo($permission);

        $targetUser = User::factory()->create([
            'password' => 'OldPassword123!',
        ]);

        $originalPasswordHash = $targetUser->password;

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->patchJson(
                "/api/v1/users/{$targetUser->id}",
                [
                    'password' => 'password',
                    'password_confirmation' => 'password',
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $targetUser->refresh();

        $this->assertSame(
            $originalPasswordHash,
            $targetUser->password,
        );

        $this->assertTrue(
            Hash::check('OldPassword123!', $targetUser->password),
        );

        $this->assertFalse(
            Hash::check('password', $targetUser->password),
        );
    }

    public function test_guest_cannot_deactivate_user(): void
    {
        $targetUser = User::factory()->create([
            'is_active' => true,
        ]);

        $response = $this->patchJson(
            "/api/v1/users/{$targetUser->id}/deactivate",
        );

        $response->assertUnauthorized();

        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'is_active' => true,
        ]);
    }

    public function test_authenticated_user_without_permission_cannot_deactivate_user(): void
    {
        $authenticatedUser = User::factory()->create();

        $targetUser = User::factory()->create([
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->patchJson(
                "/api/v1/users/{$targetUser->id}/deactivate",
            );

        $response->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'is_active' => true,
        ]);
    }

    public function test_authenticated_user_with_permission_can_deactivate_user(): void
    {
        $permission = Permission::findOrCreate('users.deactivate', 'web');

        $authenticatedUser = User::factory()->create();
        $authenticatedUser->givePermissionTo($permission);

        $targetUser = User::factory()->create([
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->patchJson(
                "/api/v1/users/{$targetUser->id}/deactivate",
            );

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $targetUser->id)
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'is_active' => false,
        ]);
    }

    public function test_deactivating_user_revokes_all_of_their_tokens(): void
    {
        $permission = Permission::findOrCreate('users.deactivate', 'web');

        $authenticatedUser = User::factory()->create();
        $authenticatedUser->givePermissionTo($permission);

        $targetUser = User::factory()->create([
            'is_active' => true,
        ]);

        $targetUser->createToken('first-token');
        $targetUser->createToken('second-token');

        $this->assertSame(2, $targetUser->tokens()->count());

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->patchJson(
                "/api/v1/users/{$targetUser->id}/deactivate",
            );

        $response->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $targetUser->id,
        ]);
    }

    public function test_user_cannot_deactivate_themselves(): void
    {
        $permission = Permission::findOrCreate('users.deactivate', 'web');

        $authenticatedUser = User::factory()->create([
            'is_active' => true,
        ]);

        $authenticatedUser->givePermissionTo($permission);

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->patchJson(
                "/api/v1/users/{$authenticatedUser->id}/deactivate",
            );

        $response
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'You cannot deactivate your own account.',
            );

        $this->assertDatabaseHas('users', [
            'id' => $authenticatedUser->id,
            'is_active' => true,
        ]);
    }

    public function test_last_super_admin_cannot_be_deactivated(): void
    {
        $permission = Permission::findOrCreate(
            'users.deactivate',
            'web',
        );

        $authenticatedUser = User::factory()->create();
        $authenticatedUser->givePermissionTo($permission);

        $superAdminRole = Role::findOrCreate(
            'super-admin',
            'web',
        );

        $lastSuperAdmin = User::factory()->create([
            'is_active' => true,
        ]);

        $lastSuperAdmin->assignRole($superAdminRole);

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->patchJson(
                "/api/v1/users/{$lastSuperAdmin->id}/deactivate",
            );

        $response
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'The last super-admin cannot be deactivated.',
            );

        $this->assertDatabaseHas('users', [
            'id' => $lastSuperAdmin->id,
            'is_active' => true,
        ]);

        $this->assertTrue(
            $lastSuperAdmin->refresh()->hasRole('super-admin'),
        );
    }

    public function test_super_admin_can_be_deactivated_when_another_active_super_admin_exists(): void
    {
        $permission = Permission::findOrCreate(
            'users.deactivate',
            'web',
        );

        $authenticatedUser = User::factory()->create();
        $authenticatedUser->givePermissionTo($permission);

        $superAdminRole = Role::findOrCreate(
            'super-admin',
            'web',
        );

        $targetSuperAdmin = User::factory()->create([
            'is_active' => true,
        ]);

        $remainingSuperAdmin = User::factory()->create([
            'is_active' => true,
        ]);

        $targetSuperAdmin->assignRole($superAdminRole);
        $remainingSuperAdmin->assignRole($superAdminRole);

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->patchJson(
                "/api/v1/users/{$targetSuperAdmin->id}/deactivate",
            );

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $targetSuperAdmin->id)
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('users', [
            'id' => $targetSuperAdmin->id,
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $remainingSuperAdmin->id,
            'is_active' => true,
        ]);

        $this->assertTrue(
            $remainingSuperAdmin->refresh()->hasRole('super-admin'),
        );
    }

    public function test_last_active_super_admin_cannot_lose_super_admin_role(): void
    {
        $permission = Permission::findOrCreate(
            'users.update',
            'web',
        );

        $authenticatedUser = User::factory()->create();
        $authenticatedUser->givePermissionTo($permission);

        $superAdminRole = Role::findOrCreate(
            'super-admin',
            'web',
        );

        $replacementRole = Role::findOrCreate(
            'property-admin',
            'web',
        );

        $lastActiveSuperAdmin = User::factory()->create([
            'is_active' => true,
        ]);

        $lastActiveSuperAdmin->assignRole($superAdminRole);

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->patchJson(
                "/api/v1/users/{$lastActiveSuperAdmin->id}",
                [
                    'role' => $replacementRole->name,
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'The last active super-admin cannot lose the super-admin role.',
            );

        $lastActiveSuperAdmin->refresh();

        $this->assertTrue(
            $lastActiveSuperAdmin->hasRole('super-admin'),
        );

        $this->assertFalse(
            $lastActiveSuperAdmin->hasRole('property-admin'),
        );
    }

    public function test_super_admin_can_lose_role_when_another_active_super_admin_exists(): void
    {
        $permission = Permission::findOrCreate(
            'users.update',
            'web',
        );

        $authenticatedUser = User::factory()->create();
        $authenticatedUser->givePermissionTo($permission);

        $superAdminRole = Role::findOrCreate(
            'super-admin',
            'web',
        );

        $replacementRole = Role::findOrCreate(
            'property-admin',
            'web',
        );

        $targetSuperAdmin = User::factory()->create([
            'is_active' => true,
        ]);

        $remainingSuperAdmin = User::factory()->create([
            'is_active' => true,
        ]);

        $targetSuperAdmin->assignRole($superAdminRole);
        $remainingSuperAdmin->assignRole($superAdminRole);

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->patchJson(
                "/api/v1/users/{$targetSuperAdmin->id}",
                [
                    'role' => $replacementRole->name,
                ],
            );

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $targetSuperAdmin->id)
            ->assertJsonPath(
                'data.roles.0',
                $replacementRole->name,
            );

        $targetSuperAdmin->refresh();
        $remainingSuperAdmin->refresh();

        $this->assertFalse(
            $targetSuperAdmin->hasRole('super-admin'),
        );

        $this->assertTrue(
            $targetSuperAdmin->hasRole('property-admin'),
        );

        $this->assertTrue(
            $remainingSuperAdmin->hasRole('super-admin'),
        );

        $this->assertTrue($remainingSuperAdmin->is_active);
    }
}
