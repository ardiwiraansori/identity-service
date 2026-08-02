<?php

namespace Tests\Feature;

use App\Models\ProjectUserAccess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProjectAccessManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_user_project_accesses(): void
    {
        $user = User::factory()->create();

        $response = $this->getJson(
            "/api/v1/users/{$user->id}/project-accesses",
        );

        $response
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_authenticated_user_without_permission_cannot_view_user_project_accesses(): void
    {
        $authenticatedUser = User::factory()->create();
        $user = User::factory()->create();

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->getJson(
                "/api/v1/users/{$user->id}/project-accesses",
            );

        $response->assertForbidden();
    }

    public function test_authenticated_user_with_permission_can_view_user_project_accesses(): void
    {
        $authenticatedUser = $this->createProjectAccessManager();
        $user = User::factory()->create();

        $secondAccess = ProjectUserAccess::query()->create([
            'user_id' => $user->id,
            'project_id' => 202,
        ]);

        $firstAccess = ProjectUserAccess::query()->create([
            'user_id' => $user->id,
            'project_id' => 101,
        ]);

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->getJson(
                "/api/v1/users/{$user->id}/project-accesses",
            );

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath(
                'data.0.id',
                $firstAccess->id,
            )
            ->assertJsonPath(
                'data.0.project_id',
                101,
            )
            ->assertJsonPath(
                'data.1.id',
                $secondAccess->id,
            )
            ->assertJsonPath(
                'data.1.project_id',
                202,
            )
            ->assertJsonMissingPath('data.0.user_id')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'project_id',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);
    }

    public function test_user_project_access_list_can_be_empty(): void
    {
        $authenticatedUser = $this->createProjectAccessManager();
        $user = User::factory()->create();

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->getJson(
                "/api/v1/users/{$user->id}/project-accesses",
            );

        $response
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_user_project_access_list_returns_not_found_for_unknown_user(): void
    {
        $authenticatedUser = $this->createProjectAccessManager();

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->getJson(
                '/api/v1/users/999999/project-accesses',
            );

        $response->assertNotFound();
    }

    public function test_guest_cannot_grant_project_access_to_user(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson(
            "/api/v1/users/{$user->id}/project-accesses",
            [
                'project_id' => 101,
            ],
        );

        $response
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);

        $this->assertDatabaseEmpty('project_user_accesses');
    }

    public function test_authenticated_user_without_permission_cannot_grant_project_access(): void
    {
        $authenticatedUser = User::factory()->create();
        $user = User::factory()->create();

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->postJson(
                "/api/v1/users/{$user->id}/project-accesses",
                [
                    'project_id' => 101,
                ],
            );

        $response->assertForbidden();

        $this->assertDatabaseEmpty('project_user_accesses');
    }

    public function test_authenticated_user_with_permission_can_grant_project_access(): void
    {
        $authenticatedUser = $this->createProjectAccessManager();
        $user = User::factory()->create();

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->postJson(
                "/api/v1/users/{$user->id}/project-accesses",
                [
                    'project_id' => 101,
                ],
            );

        $response
            ->assertCreated()
            ->assertJsonPath('data.project_id', 101)
            ->assertJsonMissingPath('data.user_id')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'project_id',
                    'created_at',
                    'updated_at',
                ],
            ]);

        $this->assertDatabaseHas('project_user_accesses', [
            'user_id' => $user->id,
            'project_id' => 101,
        ]);
    }

    public function test_project_id_is_required_when_granting_project_access(): void
    {
        $authenticatedUser = $this->createProjectAccessManager();
        $user = User::factory()->create();

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->postJson(
                "/api/v1/users/{$user->id}/project-accesses",
                [],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'project_id',
            ]);

        $this->assertDatabaseEmpty('project_user_accesses');
    }

    public function test_project_id_must_be_a_positive_integer(): void
    {
        $authenticatedUser = $this->createProjectAccessManager();
        $user = User::factory()->create();

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->postJson(
                "/api/v1/users/{$user->id}/project-accesses",
                [
                    'project_id' => 0,
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'project_id',
            ]);

        $this->assertDatabaseEmpty('project_user_accesses');
    }

    public function test_same_project_access_cannot_be_granted_twice_to_same_user(): void
    {
        $authenticatedUser = $this->createProjectAccessManager();
        $user = User::factory()->create();

        ProjectUserAccess::query()->create([
            'user_id' => $user->id,
            'project_id' => 101,
        ]);

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->postJson(
                "/api/v1/users/{$user->id}/project-accesses",
                [
                    'project_id' => 101,
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'project_id',
            ]);

        $this->assertDatabaseCount(
            'project_user_accesses',
            1,
        );
    }

    public function test_granting_project_access_returns_not_found_for_unknown_user(): void
    {
        $authenticatedUser = $this->createProjectAccessManager();

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->postJson(
                '/api/v1/users/999999/project-accesses',
                [
                    'project_id' => 101,
                ],
            );

        $response->assertNotFound();

        $this->assertDatabaseEmpty('project_user_accesses');
    }

    public function test_guest_cannot_revoke_project_access_from_user(): void
    {
        $user = User::factory()->create();

        $projectAccess = ProjectUserAccess::query()->create([
            'user_id' => $user->id,
            'project_id' => 101,
        ]);

        $response = $this->deleteJson(
            "/api/v1/users/{$user->id}/project-accesses/{$projectAccess->id}",
        );

        $response
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);

        $this->assertDatabaseHas('project_user_accesses', [
            'id' => $projectAccess->id,
        ]);
    }

    public function test_authenticated_user_without_permission_cannot_revoke_project_access(): void
    {
        $authenticatedUser = User::factory()->create();
        $user = User::factory()->create();

        $projectAccess = ProjectUserAccess::query()->create([
            'user_id' => $user->id,
            'project_id' => 101,
        ]);

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->deleteJson(
                "/api/v1/users/{$user->id}/project-accesses/{$projectAccess->id}",
            );

        $response->assertForbidden();

        $this->assertDatabaseHas('project_user_accesses', [
            'id' => $projectAccess->id,
        ]);
    }

    public function test_authenticated_user_with_permission_can_revoke_project_access(): void
    {
        $authenticatedUser = $this->createProjectAccessManager();
        $user = User::factory()->create();

        $projectAccess = ProjectUserAccess::query()->create([
            'user_id' => $user->id,
            'project_id' => 101,
        ]);

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->deleteJson(
                "/api/v1/users/{$user->id}/project-accesses/{$projectAccess->id}",
            );

        $response->assertNoContent();

        $this->assertDatabaseMissing('project_user_accesses', [
            'id' => $projectAccess->id,
        ]);
    }

    public function test_revoking_project_access_returns_not_found_for_unknown_user(): void
    {
        $authenticatedUser = $this->createProjectAccessManager();
        $user = User::factory()->create();

        $projectAccess = ProjectUserAccess::query()->create([
            'user_id' => $user->id,
            'project_id' => 101,
        ]);

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->deleteJson(
                "/api/v1/users/999999/project-accesses/{$projectAccess->id}",
            );

        $response->assertNotFound();

        $this->assertDatabaseHas('project_user_accesses', [
            'id' => $projectAccess->id,
        ]);
    }

    public function test_revoking_project_access_returns_not_found_for_unknown_access(): void
    {
        $authenticatedUser = $this->createProjectAccessManager();
        $user = User::factory()->create();

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->deleteJson(
                "/api/v1/users/{$user->id}/project-accesses/999999",
            );

        $response->assertNotFound();

        $this->assertDatabaseEmpty('project_user_accesses');
    }

    public function test_project_access_cannot_be_revoked_through_another_user(): void
    {
        $authenticatedUser = $this->createProjectAccessManager();
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        $projectAccess = ProjectUserAccess::query()->create([
            'user_id' => $firstUser->id,
            'project_id' => 101,
        ]);

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->deleteJson(
                "/api/v1/users/{$secondUser->id}/project-accesses/{$projectAccess->id}",
            );

        $response->assertNotFound();

        $this->assertDatabaseHas('project_user_accesses', [
            'id' => $projectAccess->id,
            'user_id' => $firstUser->id,
        ]);
    }

    public function test_guest_cannot_view_users_with_access_to_project(): void
    {
        $response = $this->getJson(
            '/api/v1/projects/501/users',
        );

        $response
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_authenticated_user_without_permission_cannot_view_users_with_access_to_project(): void
    {
        $authenticatedUser = User::factory()->create();

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->getJson(
                '/api/v1/projects/501/users',
            );

        $response->assertForbidden();
    }

    public function test_authenticated_user_with_permission_can_view_users_with_access_to_project(): void
    {
        $authenticatedUser = $this->createProjectAccessManager();

        $role = Role::findOrCreate(
            'project-manager',
            'web',
        );

        $zaki = User::factory()->create([
            'name' => 'Zaki Developer',
            'email' => 'zaki.project@example.com',
        ]);

        $andi = User::factory()->create([
            'name' => 'Andi Developer',
            'email' => 'andi.project@example.com',
        ]);

        $unrelatedUser = User::factory()->create([
            'name' => 'Budi Developer',
            'email' => 'budi.other-project@example.com',
        ]);

        $andi->assignRole($role);

        ProjectUserAccess::query()->create([
            'user_id' => $zaki->id,
            'project_id' => 501,
        ]);

        ProjectUserAccess::query()->create([
            'user_id' => $andi->id,
            'project_id' => 501,
        ]);

        ProjectUserAccess::query()->create([
            'user_id' => $unrelatedUser->id,
            'project_id' => 999,
        ]);

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->getJson(
                '/api/v1/projects/501/users',
            );

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $andi->id)
            ->assertJsonPath('data.0.name', 'Andi Developer')
            ->assertJsonPath(
                'data.0.email',
                'andi.project@example.com',
            )
            ->assertJsonPath(
                'data.0.roles.0',
                'project-manager',
            )
            ->assertJsonPath('data.1.id', $zaki->id)
            ->assertJsonPath('data.1.name', 'Zaki Developer')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonMissingPath('data.0.password')
            ->assertJsonMissingPath('data.0.project_accesses')
            ->assertJsonMissing([
                'id' => $unrelatedUser->id,
            ])
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'email',
                        'is_active',
                        'roles',
                        'created_at',
                        'updated_at',
                    ],
                ],
                'links',
                'meta',
            ]);
    }

    public function test_project_user_list_is_paginated(): void
    {
        $authenticatedUser = $this->createProjectAccessManager();

        for ($number = 1; $number <= 16; $number++) {
            $user = User::factory()->create([
                'name' => sprintf(
                    'Project User %02d',
                    $number,
                ),
                'email' => sprintf(
                    'project-user-%02d@example.com',
                    $number,
                ),
            ]);

            ProjectUserAccess::query()->create([
                'user_id' => $user->id,
                'project_id' => 501,
            ]);
        }

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->getJson(
                '/api/v1/projects/501/users',
            );

        $response
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath(
                'data.0.name',
                'Project User 01',
            )
            ->assertJsonPath(
                'data.14.name',
                'Project User 15',
            )
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 16);
    }

    public function test_project_user_list_can_be_empty(): void
    {
        $authenticatedUser = $this->createProjectAccessManager();

        $response = $this
            ->actingAs($authenticatedUser, 'sanctum')
            ->getJson(
                '/api/v1/projects/999999/users',
            );

        $response
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('meta.per_page', 15);
    }

    /**
     * Membuat user yang memiliki izin mengelola akses project.
     */
    private function createProjectAccessManager(): User
    {
        $permission = Permission::findOrCreate(
            'project-access.manage',
            'web',
        );

        $authenticatedUser = User::factory()->create();
        $authenticatedUser->givePermissionTo($permission);

        return $authenticatedUser;
    }
}
