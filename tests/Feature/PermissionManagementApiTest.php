<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PermissionManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_permission_list(): void
    {
        $response = $this->getJson('/api/v1/permissions');

        $response
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_authenticated_user_without_permission_cannot_view_permission_list(): void
    {
        $authenticatedUser = User::factory()->create();

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->getJson('/api/v1/permissions');

        $response->assertForbidden();
    }

    public function test_authenticated_user_with_permission_can_view_permission_list(): void
    {
        $viewPermission = Permission::findOrCreate(
            'permissions.view',
            'web',
        );

        $authenticatedUser = User::factory()->create();
        $authenticatedUser->givePermissionTo($viewPermission);

        Permission::findOrCreate(
            'users.view',
            'web',
        );

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->getJson('/api/v1/permissions');

        $response
            ->assertOk()
            ->assertJsonFragment([
                'name' => 'permissions.view',
            ])
            ->assertJsonFragment([
                'name' => 'users.view',
            ])
            ->assertJsonMissing([
                'guard_name' => 'web',
            ])
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'created_at',
                        'updated_at',
                    ],
                ],
                'links',
                'meta',
            ]);
    }

    public function test_permission_list_only_returns_permissions_using_web_guard(): void
    {
        $viewPermission = Permission::findOrCreate(
            'permissions.view',
            'web',
        );

        $authenticatedUser = User::factory()->create();
        $authenticatedUser->givePermissionTo($viewPermission);

        Permission::findOrCreate(
            'users.view',
            'web',
        );

        Permission::findOrCreate(
            'internal.api-access',
            'api',
        );

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->getJson('/api/v1/permissions');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment([
                'name' => 'permissions.view',
            ])
            ->assertJsonFragment([
                'name' => 'users.view',
            ])
            ->assertJsonMissing([
                'name' => 'internal.api-access',
            ]);
    }
}
