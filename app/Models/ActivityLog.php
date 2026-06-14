<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'action', 'module', 'description', 'ip_address', 'properties'])]
class ActivityLog extends Model
{
    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public static function record(string $action, string $module, string $description, array $properties = []): void
    {
        self::query()->create([
            'user_id' => auth()->id(),
            'action' => $action,
            'module' => $module,
            'description' => $description,
            'ip_address' => request()?->ip(),
            'properties' => $properties,
        ]);
    }
}
