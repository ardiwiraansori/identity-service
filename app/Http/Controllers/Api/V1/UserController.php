<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreUserRequest;
use App\Http\Requests\Api\V1\UpdateUserRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
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
        $user = DB::transaction(function () use ($request, $user): User {
            $validated = $request->validated();

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
        });

        return new UserResource($user);
    }
}
