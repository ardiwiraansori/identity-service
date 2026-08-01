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
}
