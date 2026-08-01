<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreRoleRequest;
use App\Http\Requests\Api\V1\UpdateRoleRequest;
use App\Http\Resources\Api\V1\RoleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;

class RoleController extends Controller
{
    /**
     * Menampilkan daftar role beserta permission.
     */
    public function index(): AnonymousResourceCollection
    {
        $roles = Role::query()
            ->with('permissions')
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->paginate(15);

        return RoleResource::collection($roles);
    }

    /**
     * Membuat role baru beserta permission.
     */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = DB::transaction(function () use ($request): Role {
            $validated = $request->validated();

            $role = Role::query()->create([
                'name' => $validated['name'],
                'guard_name' => 'web',
            ]);

            $role->syncPermissions($validated['permissions']);

            return $role->load('permissions');
        });

        return (new RoleResource($role))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Memperbarui nama dan permission role.
     *
     * @throws ValidationException
     */
    public function update(
        UpdateRoleRequest $request,
        Role $role,
    ): JsonResponse {
        abort_unless(
            $role->guard_name === 'web',
            Response::HTTP_NOT_FOUND,
        );

        if ($role->name === 'super-admin') {
            throw ValidationException::withMessages([
                'role' => [
                    'Role super-admin tidak dapat diubah.',
                ],
            ]);
        }

        $role = DB::transaction(
            function () use ($request, $role): Role {
                $validated = $request->validated();

                if (array_key_exists('name', $validated)) {
                    $role->name = $validated['name'];
                    $role->save();
                }

                if (array_key_exists('permissions', $validated)) {
                    $role->syncPermissions(
                        $validated['permissions'],
                    );
                }

                return $role->load('permissions');
            },
        );

        return (new RoleResource($role))->response();
    }
}
