<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    /**
     * Otorisasi endpoint ditangani oleh middleware users.create.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Aturan validasi untuk membuat user.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                'unique:users,email',
            ],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
            'role' => [
                'required',
                'string',
                Rule::exists('roles', 'name')
                    ->where(
                        fn(Builder $query): Builder => $query
                            ->where('guard_name', 'web'),
                    ),
            ],
        ];
    }
}
