<?php

namespace App\Models;

use App\Support\Tenant;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $casts = ['old_values' => 'array', 'new_values' => 'array', 'created_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $action, ?Model $model = null, ?string $description = null, array $old = [], array $new = []): self
    {
        $req = request();

        return static::create([
            'institution_id' => $model?->institution_id ?? Tenant::id(),
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => $model ? class_basename($model) : null,
            'auditable_id' => $model?->getKey(),
            'description' => $description,
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip' => $req?->ip(),
            'user_agent' => substr((string) $req?->userAgent(), 0, 250) ?: null,
        ]);
    }
}
