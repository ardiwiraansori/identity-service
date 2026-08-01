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
}
