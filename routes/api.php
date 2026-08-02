<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\PermissionController;
use App\Http\Controllers\Api\V1\ProjectAccessController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/auth')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::prefix('v1')
    ->middleware('auth:sanctum')
    ->group(function (): void {
        Route::get('/users', [UserController::class, 'index'])
            ->middleware('can:users.view');

        Route::post('/users', [UserController::class, 'store'])
            ->middleware('can:users.create');

        Route::get('/users/{user}', [UserController::class, 'show'])
            ->middleware('can:users.view');

        Route::patch('/users/{user}', [UserController::class, 'update'])
            ->middleware('can:users.update');

        Route::patch('/users/{user}/deactivate', [UserController::class, 'deactivate'])
            ->middleware('can:users.deactivate');

        Route::get(
            '/users/{user}/project-accesses',
            [ProjectAccessController::class, 'index'],
        )->middleware('can:project-access.manage');

        Route::post(
            '/users/{user}/project-accesses',
            [ProjectAccessController::class, 'store'],
        )->middleware('can:project-access.manage');

        Route::delete(
            '/users/{user}/project-accesses/{projectAccess}',
            [ProjectAccessController::class, 'destroy'],
        )
            ->middleware('can:project-access.manage')
            ->scopeBindings();

        Route::get('/roles', [RoleController::class, 'index'])
            ->middleware('can:roles.view');

        Route::post('/roles', [RoleController::class, 'store'])
            ->middleware('can:roles.manage');

        Route::patch('/roles/{role}', [RoleController::class, 'update'])
            ->middleware('can:roles.manage');

        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])
            ->middleware('can:roles.manage');

        Route::get('/roles/{role}', [RoleController::class, 'show'])
            ->middleware('can:roles.view');

        Route::get('/permissions', [PermissionController::class, 'index'])
            ->middleware('can:permissions.view');
    });
