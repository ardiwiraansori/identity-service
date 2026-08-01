<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_role_list(): void
    {
        $response = $this->getJson('/api/v1/roles');

        $response
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_authenticated_user_without_permission_cannot_view_role_list(): void
    {
        $authenticatedUser = User::factory()->create();

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->getJson('/api/v1/roles');

        $response->assertForbidden();
    }

    public function test_authenticated_user_with_permission_can_view_role_list(): void
    {
        $permission = Permission::findOrCreate(
            'roles.view',
            'web',
        );

        $authenticatedUser = User::factory()->create();
        $authenticatedUser->givePermissionTo($permission);

        $rolePermission = Permission::findOrCreate(
            'users.view',
            'web',
        );

        $role = Role::findOrCreate(
            'property-admin',
            'web',
        );

        $role->givePermissionTo($rolePermission);

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->getJson('/api/v1/roles');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.id', $role->id)
            ->assertJsonPath('data.0.name', 'property-admin')
            ->assertJsonPath(
                'data.0.permissions.0',
                'users.view',
            )
            ->assertJsonMissingPath('data.0.guard_name')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'permissions',
                        'created_at',
                        'updated_at',
                    ],
                ],
                'links',
                'meta',
            ]);
    }

    public function test_role_list_only_returns_roles_using_web_guard(): void
    {
        $permission = Permission::findOrCreate(
            'roles.view',
            'web',
        );

        $authenticatedUser = User::factory()->create();
        $authenticatedUser->givePermissionTo($permission);

        $webRole = Role::findOrCreate(
            'property-admin',
            'web',
        );

        Role::findOrCreate(
            'api-only-role',
            'api',
        );

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->getJson('/api/v1/roles');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $webRole->id)
            ->assertJsonPath('data.0.name', 'property-admin')
            ->assertJsonMissing([
                'name' => 'api-only-role',
            ]);
    }

    public function test_guest_cannot_create_role(): void
    {
        $response = $this->postJson('/api/v1/roles', [
            'name' => 'finance-manager',
            'permissions' => [
                'users.view',
            ],
        ]);

        $response
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);

        $this->assertDatabaseMissing('roles', [
            'name' => 'finance-manager',
            'guard_name' => 'web',
        ]);
    }

    public function test_authenticated_user_without_permission_cannot_create_role(): void
    {
        $authenticatedUser = User::factory()->create();

        Permission::findOrCreate(
            'users.view',
            'web',
        );

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->postJson('/api/v1/roles', [
                'name' => 'finance-manager',
                'permissions' => [
                    'users.view',
                ],
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('roles', [
            'name' => 'finance-manager',
            'guard_name' => 'web',
        ]);
    }

    public function test_authenticated_user_with_permission_can_create_role(): void
    {
        $manageRolesPermission = Permission::findOrCreate(
            'roles.manage',
            'web',
        );

        $usersViewPermission = Permission::findOrCreate(
            'users.view',
            'web',
        );

        $usersCreatePermission = Permission::findOrCreate(
            'users.create',
            'web',
        );

        $authenticatedUser = User::factory()->create();
        $authenticatedUser->givePermissionTo($manageRolesPermission);

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->postJson('/api/v1/roles', [
                'name' => 'finance-manager',
                'permissions' => [
                    'users.view',
                    'users.create',
                ],
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'finance-manager')
            ->assertJsonPath('data.permissions.0', 'users.create')
            ->assertJsonPath('data.permissions.1', 'users.view')
            ->assertJsonMissingPath('data.guard_name')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'permissions',
                    'created_at',
                    'updated_at',
                ],
            ]);

        $role = Role::query()
            ->where('name', 'finance-manager')
            ->where('guard_name', 'web')
            ->firstOrFail();

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'finance-manager',
            'guard_name' => 'web',
        ]);

        $this->assertDatabaseHas('role_has_permissions', [
            'role_id' => $role->id,
            'permission_id' => $usersViewPermission->id,
        ]);

        $this->assertDatabaseHas('role_has_permissions', [
            'role_id' => $role->id,
            'permission_id' => $usersCreatePermission->id,
        ]);
    }

    public function test_create_role_rejects_duplicate_name_for_web_guard(): void
    {
        $manageRolesPermission = Permission::findOrCreate(
            'roles.manage',
            'web',
        );

        Permission::findOrCreate(
            'users.view',
            'web',
        );

        Role::findOrCreate(
            'finance-manager',
            'web',
        );

        $authenticatedUser = User::factory()->create();
        $authenticatedUser->givePermissionTo($manageRolesPermission);

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->postJson('/api/v1/roles', [
                'name' => 'finance-manager',
                'permissions' => [
                    'users.view',
                ],
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
            ]);

        $this->assertDatabaseCount('roles', 1);
    }

    public function test_create_role_rejects_invalid_permission(): void
    {
        $manageRolesPermission = Permission::findOrCreate(
            'roles.manage',
            'web',
        );

        $authenticatedUser = User::factory()->create();
        $authenticatedUser->givePermissionTo($manageRolesPermission);

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->postJson('/api/v1/roles', [
                'name' => 'finance-manager',
                'permissions' => [
                    'permission.does-not-exist',
                ],
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'permissions.0',
            ]);

        $this->assertDatabaseMissing('roles', [
            'name' => 'finance-manager',
            'guard_name' => 'web',
        ]);
    }

    public function test_create_role_rejects_permission_using_another_guard(): void
    {
        $manageRolesPermission = Permission::findOrCreate(
            'roles.manage',
            'web',
        );

        Permission::findOrCreate(
            'internal.api-access',
            'api',
        );

        $authenticatedUser = User::factory()->create();
        $authenticatedUser->givePermissionTo($manageRolesPermission);

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->postJson('/api/v1/roles', [
                'name' => 'finance-manager',
                'permissions' => [
                    'internal.api-access',
                ],
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'permissions.0',
            ]);

        $this->assertDatabaseMissing('roles', [
            'name' => 'finance-manager',
            'guard_name' => 'web',
        ]);
    }

    public function test_create_role_rejects_duplicate_permissions(): void
    {
        $manageRolesPermission = Permission::findOrCreate(
            'roles.manage',
            'web',
        );

        Permission::findOrCreate(
            'users.view',
            'web',
        );

        $authenticatedUser = User::factory()->create();
        $authenticatedUser->givePermissionTo($manageRolesPermission);

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->postJson('/api/v1/roles', [
                'name' => 'finance-manager',
                'permissions' => [
                    'users.view',
                    'users.view',
                ],
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'permissions.0',
                'permissions.1',
            ]);

        $this->assertDatabaseMissing('roles', [
            'name' => 'finance-manager',
            'guard_name' => 'web',
        ]);
    }

    public function test_create_role_requires_at_least_one_permission(): void
    {
        $manageRolesPermission = Permission::findOrCreate(
            'roles.manage',
            'web',
        );

        $authenticatedUser = User::factory()->create();
        $authenticatedUser->givePermissionTo($manageRolesPermission);

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->postJson('/api/v1/roles', [
                'name' => 'finance-manager',
                'permissions' => [],
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'permissions',
            ]);

        $this->assertDatabaseMissing('roles', [
            'name' => 'finance-manager',
            'guard_name' => 'web',
        ]);
    }

    public function test_guest_cannot_update_role(): void
    {
        $role = Role::findOrCreate(
            'finance-manager',
            'web',
        );

        $response = $this->patchJson(
            "/api/v1/roles/{$role->id}",
            [
                'name' => 'finance-supervisor',
            ],
        );

        $response
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'finance-manager',
            'guard_name' => 'web',
        ]);
    }

    public function test_authenticated_user_without_permission_cannot_update_role(): void
    {
        $authenticatedUser = User::factory()->create();

        $role = Role::findOrCreate(
            'finance-manager',
            'web',
        );

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->patchJson(
                "/api/v1/roles/{$role->id}",
                [
                    'name' => 'finance-supervisor',
                ],
            );

        $response->assertForbidden();

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'finance-manager',
            'guard_name' => 'web',
        ]);
    }

    public function test_authenticated_user_with_permission_can_update_role_name(): void
    {
        $authenticatedUser = $this->createRoleManager();

        $usersViewPermission = Permission::findOrCreate(
            'users.view',
            'web',
        );

        $role = Role::findOrCreate(
            'finance-manager',
            'web',
        );

        $role->givePermissionTo($usersViewPermission);

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->patchJson(
                "/api/v1/roles/{$role->id}",
                [
                    'name' => 'finance-supervisor',
                ],
            );

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $role->id)
            ->assertJsonPath('data.name', 'finance-supervisor')
            ->assertJsonPath(
                'data.permissions.0',
                'users.view',
            )
            ->assertJsonMissingPath('data.guard_name');

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'finance-supervisor',
            'guard_name' => 'web',
        ]);

        $this->assertDatabaseMissing('roles', [
            'id' => $role->id,
            'name' => 'finance-manager',
            'guard_name' => 'web',
        ]);

        $this->assertDatabaseHas('role_has_permissions', [
            'role_id' => $role->id,
            'permission_id' => $usersViewPermission->id,
        ]);
    }

    public function test_update_role_allows_existing_name_of_the_same_role(): void
    {
        $authenticatedUser = $this->createRoleManager();

        $role = Role::findOrCreate(
            'finance-manager',
            'web',
        );

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->patchJson(
                "/api/v1/roles/{$role->id}",
                [
                    'name' => 'finance-manager',
                ],
            );

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $role->id)
            ->assertJsonPath('data.name', 'finance-manager');

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'finance-manager',
            'guard_name' => 'web',
        ]);
    }

    public function test_update_role_rejects_name_used_by_another_web_role(): void
    {
        $authenticatedUser = $this->createRoleManager();

        Role::findOrCreate(
            'finance-manager',
            'web',
        );

        $role = Role::findOrCreate(
            'accounting-manager',
            'web',
        );

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->patchJson(
                "/api/v1/roles/{$role->id}",
                [
                    'name' => 'finance-manager',
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
            ]);

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'accounting-manager',
            'guard_name' => 'web',
        ]);
    }

    public function test_authenticated_user_with_permission_can_update_role_permissions(): void
    {
        $authenticatedUser = $this->createRoleManager();

        $usersViewPermission = Permission::findOrCreate(
            'users.view',
            'web',
        );

        $usersCreatePermission = Permission::findOrCreate(
            'users.create',
            'web',
        );

        $usersUpdatePermission = Permission::findOrCreate(
            'users.update',
            'web',
        );

        $role = Role::findOrCreate(
            'finance-manager',
            'web',
        );

        $role->givePermissionTo($usersViewPermission);

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->patchJson(
                "/api/v1/roles/{$role->id}",
                [
                    'permissions' => [
                        'users.create',
                        'users.update',
                    ],
                ],
            );

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $role->id)
            ->assertJsonPath('data.name', 'finance-manager')
            ->assertJsonPath(
                'data.permissions.0',
                'users.create',
            )
            ->assertJsonPath(
                'data.permissions.1',
                'users.update',
            );

        $this->assertDatabaseMissing('role_has_permissions', [
            'role_id' => $role->id,
            'permission_id' => $usersViewPermission->id,
        ]);

        $this->assertDatabaseHas('role_has_permissions', [
            'role_id' => $role->id,
            'permission_id' => $usersCreatePermission->id,
        ]);

        $this->assertDatabaseHas('role_has_permissions', [
            'role_id' => $role->id,
            'permission_id' => $usersUpdatePermission->id,
        ]);
    }

    public function test_update_role_rejects_invalid_permission(): void
    {
        $authenticatedUser = $this->createRoleManager();

        $role = Role::findOrCreate(
            'finance-manager',
            'web',
        );

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->patchJson(
                "/api/v1/roles/{$role->id}",
                [
                    'permissions' => [
                        'permission.does-not-exist',
                    ],
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'permissions.0',
            ]);
    }

    public function test_update_role_rejects_permission_using_another_guard(): void
    {
        $authenticatedUser = $this->createRoleManager();

        Permission::findOrCreate(
            'internal.api-access',
            'api',
        );

        $role = Role::findOrCreate(
            'finance-manager',
            'web',
        );

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->patchJson(
                "/api/v1/roles/{$role->id}",
                [
                    'permissions' => [
                        'internal.api-access',
                    ],
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'permissions.0',
            ]);
    }

    public function test_update_role_rejects_duplicate_permissions(): void
    {
        $authenticatedUser = $this->createRoleManager();

        Permission::findOrCreate(
            'users.view',
            'web',
        );

        $role = Role::findOrCreate(
            'finance-manager',
            'web',
        );

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->patchJson(
                "/api/v1/roles/{$role->id}",
                [
                    'permissions' => [
                        'users.view',
                        'users.view',
                    ],
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'permissions.0',
                'permissions.1',
            ]);
    }

    public function test_update_role_requires_at_least_one_permission_when_provided(): void
    {
        $authenticatedUser = $this->createRoleManager();

        $role = Role::findOrCreate(
            'finance-manager',
            'web',
        );

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->patchJson(
                "/api/v1/roles/{$role->id}",
                [
                    'permissions' => [],
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'permissions',
            ]);
    }

    public function test_role_using_another_guard_cannot_be_updated(): void
    {
        $authenticatedUser = $this->createRoleManager();

        $role = Role::findOrCreate(
            'api-manager',
            'api',
        );

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->patchJson(
                "/api/v1/roles/{$role->id}",
                [
                    'name' => 'renamed-api-manager',
                ],
            );

        $response->assertNotFound();

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'api-manager',
            'guard_name' => 'api',
        ]);
    }

    public function test_super_admin_role_cannot_be_updated(): void
    {
        $authenticatedUser = $this->createRoleManager();

        $role = Role::findOrCreate(
            'super-admin',
            'web',
        );

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->patchJson(
                "/api/v1/roles/{$role->id}",
                [
                    'name' => 'renamed-super-admin',
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'role',
            ]);

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'super-admin',
            'guard_name' => 'web',
        ]);
    }

    /**
     * Membuat user yang memiliki izin mengelola role.
     */
    private function createRoleManager(): User
    {
        $manageRolesPermission = Permission::findOrCreate(
            'roles.manage',
            'web',
        );

        $authenticatedUser = User::factory()->create();
        $authenticatedUser->givePermissionTo(
            $manageRolesPermission,
        );

        return $authenticatedUser;
    }

    public function test_guest_cannot_delete_role(): void
    {
        $role = Role::findOrCreate(
            'finance-manager',
            'web',
        );

        $response = $this->deleteJson(
            "/api/v1/roles/{$role->id}",
        );

        $response
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'finance-manager',
            'guard_name' => 'web',
        ]);
    }

    public function test_authenticated_user_without_permission_cannot_delete_role(): void
    {
        $authenticatedUser = User::factory()->create();

        $role = Role::findOrCreate(
            'finance-manager',
            'web',
        );

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->deleteJson(
                "/api/v1/roles/{$role->id}",
            );

        $response->assertForbidden();

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'finance-manager',
            'guard_name' => 'web',
        ]);
    }

    public function test_authenticated_user_with_permission_can_delete_unused_role(): void
    {
        $authenticatedUser = $this->createRoleManager();

        $usersViewPermission = Permission::findOrCreate(
            'users.view',
            'web',
        );

        $role = Role::findOrCreate(
            'finance-manager',
            'web',
        );

        $role->givePermissionTo($usersViewPermission);

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->deleteJson(
                "/api/v1/roles/{$role->id}",
            );

        $response->assertNoContent();

        $this->assertDatabaseMissing('roles', [
            'id' => $role->id,
        ]);

        $this->assertDatabaseMissing('role_has_permissions', [
            'role_id' => $role->id,
            'permission_id' => $usersViewPermission->id,
        ]);
    }

    public function test_role_assigned_to_user_cannot_be_deleted(): void
    {
        $authenticatedUser = $this->createRoleManager();

        $role = Role::findOrCreate(
            'finance-manager',
            'web',
        );

        $assignedUser = User::factory()->create();
        $assignedUser->assignRole($role);

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->deleteJson(
                "/api/v1/roles/{$role->id}",
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'role',
            ]);

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'finance-manager',
            'guard_name' => 'web',
        ]);

        $this->assertDatabaseHas('model_has_roles', [
            'role_id' => $role->id,
            'model_type' => User::class,
            'model_id' => $assignedUser->id,
        ]);
    }

    public function test_super_admin_role_cannot_be_deleted(): void
    {
        $authenticatedUser = $this->createRoleManager();

        $role = Role::findOrCreate(
            'super-admin',
            'web',
        );

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->deleteJson(
                "/api/v1/roles/{$role->id}",
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'role',
            ]);

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'super-admin',
            'guard_name' => 'web',
        ]);
    }

    public function test_role_using_another_guard_cannot_be_deleted(): void
    {
        $authenticatedUser = $this->createRoleManager();

        $role = Role::findOrCreate(
            'api-manager',
            'api',
        );

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->deleteJson(
                "/api/v1/roles/{$role->id}",
            );

        $response->assertNotFound();

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'api-manager',
            'guard_name' => 'api',
        ]);
    }
}
