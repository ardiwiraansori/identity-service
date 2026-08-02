<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'project_id',
])]
class ProjectUserAccess extends Model
{
    /**
     * User yang memperoleh akses ke project.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
