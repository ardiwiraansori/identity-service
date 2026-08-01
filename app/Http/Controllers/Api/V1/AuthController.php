<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login dan membuat personal access token.
     *
     * @throws ValidationException
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::query()
            ->where('email', $validated['email'])
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password tidak sesuai.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Akun pengguna tidak aktif.'],
            ]);
        }

        $token = $user
            ->createToken($validated['device_name'] ?? 'react-web')
            ->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil.',
            'data' => [
                'token_type' => 'Bearer',
                'access_token' => $token,
                'user' => $user,
            ],
        ]);
    }

    /**
     * Menampilkan user yang sedang login.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => [
                'user' => $user,
                'roles' => $user->getRoleNames()->values(),
                'permissions' => $user
                    ->getAllPermissions()
                    ->pluck('name')
                    ->values(),
            ],
        ]);
    }

    /**
     * Menghapus token yang sedang digunakan.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logout berhasil.',
        ]);
    }
}
