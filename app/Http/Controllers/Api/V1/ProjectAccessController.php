<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreProjectAccessRequest;
use App\Http\Resources\Api\V1\ProjectUserAccessResource;
use App\Models\ProjectUserAccess;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProjectAccessController extends Controller
{
    /**
     * Menampilkan daftar akses project milik user.
     */
    public function index(User $user): AnonymousResourceCollection
    {
        $projectAccesses = $user
            ->projectAccesses()
            ->orderBy('project_id')
            ->get();

        return ProjectUserAccessResource::collection(
            $projectAccesses,
        );
    }

    /**
     * Memberikan akses project kepada user.
     */
    public function store(
        StoreProjectAccessRequest $request,
        User $user,
    ): JsonResponse {
        $projectAccess = $user
            ->projectAccesses()
            ->create($request->validated());

        return (new ProjectUserAccessResource($projectAccess))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Mencabut akses project dari user.
     */
    public function destroy(
        User $user,
        ProjectUserAccess $projectAccess,
    ): Response {
        abort_unless(
            $projectAccess->user_id === $user->id,
            Response::HTTP_NOT_FOUND,
        );

        $projectAccess->delete();

        return response()->noContent();
    }
}
