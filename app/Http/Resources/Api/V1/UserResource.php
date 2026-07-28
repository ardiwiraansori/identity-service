<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Format data user untuk response API.
 *
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
{
    /**
     * Mengubah model user menjadi data response API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'is_active' => $this->is_active,
            'roles' => $this->whenLoaded(
                'roles',
                fn() => $this->roles
                    ->pluck('name')
                    ->values(),
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
