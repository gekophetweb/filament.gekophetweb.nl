<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'tool', 'arguments', 'status'])]
class McpAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['arguments' => 'array', 'created_at' => 'datetime'];
    }
}
