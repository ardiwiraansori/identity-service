<?php

namespace Tests\Feature;

use App\Models\ProjectUserAccess;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectUserAccessModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_many_project_accesses(): void
    {
        $user = User::factory()->create();

        $firstAccess = ProjectUserAccess::query()->create([
            'user_id' => $user->id,
            'project_id' => 101,
        ]);

        $secondAccess = ProjectUserAccess::query()->create([
            'user_id' => $user->id,
            'project_id' => 202,
        ]);

        $projectAccesses = $user
            ->projectAccesses()
            ->orderBy('project_id')
            ->get();

        $this->assertCount(2, $projectAccesses);
        $this->assertSame(
            [
                $firstAccess->id,
                $secondAccess->id,
            ],
            $projectAccesses->pluck('id')->all(),
        );
    }

    public function test_project_user_access_belongs_to_user(): void
    {
        $user = User::factory()->create();

        $projectAccess = ProjectUserAccess::query()->create([
            'user_id' => $user->id,
            'project_id' => 101,
        ]);

        $this->assertTrue(
            $projectAccess->user->is($user),
        );
    }

    public function test_same_project_cannot_be_assigned_twice_to_same_user(): void
    {
        $user = User::factory()->create();

        ProjectUserAccess::query()->create([
            'user_id' => $user->id,
            'project_id' => 101,
        ]);

        $this->expectException(QueryException::class);

        ProjectUserAccess::query()->create([
            'user_id' => $user->id,
            'project_id' => 101,
        ]);
    }

    public function test_same_project_can_be_assigned_to_different_users(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        ProjectUserAccess::query()->create([
            'user_id' => $firstUser->id,
            'project_id' => 101,
        ]);

        ProjectUserAccess::query()->create([
            'user_id' => $secondUser->id,
            'project_id' => 101,
        ]);

        $this->assertDatabaseHas('project_user_accesses', [
            'user_id' => $firstUser->id,
            'project_id' => 101,
        ]);

        $this->assertDatabaseHas('project_user_accesses', [
            'user_id' => $secondUser->id,
            'project_id' => 101,
        ]);

        $this->assertDatabaseCount(
            'project_user_accesses',
            2,
        );
    }

    public function test_project_accesses_are_deleted_when_user_is_deleted(): void
    {
        $user = User::factory()->create();

        $projectAccess = ProjectUserAccess::query()->create([
            'user_id' => $user->id,
            'project_id' => 101,
        ]);

        $this->assertDatabaseHas('project_user_accesses', [
            'id' => $projectAccess->id,
        ]);

        $user->delete();

        $this->assertDatabaseMissing('project_user_accesses', [
            'id' => $projectAccess->id,
        ]);
    }
}
