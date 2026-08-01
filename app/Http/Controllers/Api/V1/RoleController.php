<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\RoleResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\Permission\Models\Role;

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
}
