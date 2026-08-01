<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Role;

/**
 * Format data role untuk response API.
 *
 * @mixin Role
 */
class RoleResource extends JsonResource
{
    /**
     * Mengubah model role menjadi data response API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'permissions' => $this->whenLoaded(
                'permissions',
                fn () => $this->permissions
                    ->pluck('name')
                    ->sort()
                    ->values(),
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
