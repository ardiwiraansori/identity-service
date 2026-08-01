<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    /**
     * Otorisasi endpoint ditangani oleh middleware roles.manage.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Aturan validasi untuk membuat role.
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
                Rule::unique('roles', 'name')
                    ->where(
                        fn (Builder $query): Builder => $query
                            ->where('guard_name', 'web'),
                    ),
            ],
            'permissions' => [
                'required',
                'array',
                'min:1',
            ],
            'permissions.*' => [
                'required',
                'string',
                'distinct',
                Rule::exists('permissions', 'name')
                    ->where(
                        fn (Builder $query): Builder => $query
                            ->where('guard_name', 'web'),
                    ),
            ],
        ];
    }
}
