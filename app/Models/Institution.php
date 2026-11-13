<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Institution extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'modules' => 'array',
        'settings' => 'array',
        'license_expires_at' => 'date',
    ];

    public const PLANS = ['basico' => 'Básico', 'profesional' => 'Profesional', 'enterprise' => 'Enterprise'];

    public function campuses() { return $this->hasMany(Campus::class); }
    public function users() { return $this->hasMany(User::class); }
    public function students() { return $this->hasMany(Student::class); }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? route('archivos.logo', $this) : null;
    }

    public function isActive(): bool
    {
        return $this->status !== 'suspendida'
            && (! $this->license_expires_at || $this->license_expires_at->copy()->endOfDay()->isFuture());
    }
}
