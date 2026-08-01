<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreRoleRequest;
use App\Http\Resources\Api\V1\RoleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
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
}
