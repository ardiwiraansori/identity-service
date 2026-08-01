<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_authenticated_profile(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_authenticated_user_can_view_profile_roles_and_permissions(): void
    {
        $permission = Permission::query()->create([
            'name' => 'users.view',
            'guard_name' => 'web',
        ]);

        $role = Role::query()->create([
            'name' => 'super-admin',
            'guard_name' => 'web',
        ]);

        $role->givePermissionTo($permission);

        $user = User::factory()->create();

        $user->assignRole($role);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/auth/me');

        $response
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonPath('data.roles.0', 'super-admin')
            ->assertJsonPath('data.permissions.0', 'users.view');
    }

    public function test_user_can_login_access_profile_and_logout(): void
    {
        $user = User::factory()->create([
            'name' => 'Test Administrator',
            'email' => 'test-admin@property.test',
            'password' => 'Secret123!',
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'test-admin@property.test',
            'password' => 'Secret123!',
            'device_name' => 'automated-test',
        ]);

        $loginResponse
            ->assertOk()
            ->assertJsonPath('message', 'Login berhasil.')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.email', 'test-admin@property.test')
            ->assertJsonStructure([
                'data' => [
                    'access_token',
                ],
            ]);

        $token = $loginResponse->json('data.access_token');

        $this->assertNotEmpty($token);
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id);

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJson([
                'message' => 'Logout berhasil.',
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);

        // Feature test memakai aplikasi yang sama untuk beberapa request.
        // Bersihkan guard agar request berikutnya membaca ulang token dari database.
        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'inactive-user@property.test',
            'password' => 'Secret123!',
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'inactive-user@property.test',
            'password' => 'Secret123!',
            'device_name' => 'automated-test',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email'])
            ->assertJsonPath(
                'errors.email.0',
                'Akun pengguna tidak aktif.',
            );

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
