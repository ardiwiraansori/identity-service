<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UpdateRoleRequest extends FormRequest
{
    /**
     * Otorisasi endpoint ditangani oleh middleware roles.manage.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Aturan validasi untuk memperbarui role.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Role $role */
        $role = $this->route('role');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')
                    ->ignore($role->id)
                    ->where(
                        fn (Builder $query): Builder => $query
                            ->where('guard_name', 'web'),
                    ),
            ],
            'permissions' => [
                'sometimes',
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
