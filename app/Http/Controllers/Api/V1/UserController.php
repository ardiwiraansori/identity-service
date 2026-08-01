<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreUserRequest;
use App\Http\Requests\Api\V1\UpdateUserRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    /**
     * Menampilkan daftar user.
     */
    public function index(): AnonymousResourceCollection
    {
        $users = User::query()
            ->with('roles')
            ->orderBy('name')
            ->paginate(15);

        return UserResource::collection($users);
    }

    /**
     * Membuat user baru.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = DB::transaction(function () use ($validated): User {
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);

            $user->assignRole($validated['role']);

            $user->refresh();

            return $user->load('roles');
        });

        return response()->json([
            'message' => 'User berhasil dibuat.',
            'data' => new UserResource($user),
        ], 201);
    }

    /**
     * Menampilkan detail satu user.
     */
    public function show(User $user): UserResource
    {
        $user->load('roles');

        return new UserResource($user);
    }

    /**
     * Memperbarui data user.
     */
    public function update(
        UpdateUserRequest $request,
        User $user,
    ): UserResource {
        $validated = $request->validated();

        $isRemovingLastActiveSuperAdminRole =
            array_key_exists('role', $validated)
            && $validated['role'] !== 'super-admin'
            && $user->is_active
            && $user->hasRole('super-admin')
            && User::role('super-admin')
                ->where('is_active', true)
                ->count() <= 1;

        if ($isRemovingLastActiveSuperAdminRole) {
            abort(
                422,
                'The last active super-admin cannot lose the super-admin role.',
            );
        }

        $user = DB::transaction(
            function () use ($request, $validated, $user): User {
                $user->fill(
                    $request->safe()->only([
                        'name',
                        'email',
                        'password',
                    ]),
                );

                $user->save();

                if (array_key_exists('role', $validated)) {
                    $user->syncRoles([$validated['role']]);
                }

                return $user->refresh()->load('roles');
            },
        );

        return new UserResource($user);
    }

    /**
     * Menonaktifkan akun user dan mencabut seluruh token aksesnya.
     */
    public function deactivate(
        Request $request,
        User $user,
    ): UserResource {
        if ($request->user()->is($user)) {
            abort(422, 'You cannot deactivate your own account.');
        }

        $isLastActiveSuperAdmin = $user->is_active
            && $user->hasRole('super-admin')
            && User::role('super-admin')
                ->where('is_active', true)
                ->count() <= 1;

        if ($isLastActiveSuperAdmin) {
            abort(
                422,
                'The last super-admin cannot be deactivated.',
            );
        }

        $user = DB::transaction(function () use ($user): User {
            $user->is_active = false;
            $user->save();

            $user->tokens()->delete();

            return $user->refresh()->load('roles');
        });

        return new UserResource($user);
    }
}
