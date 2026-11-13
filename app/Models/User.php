<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes, Auditable;

    protected $guarded = ['id'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'is_active' => 'boolean',
        'must_change_password' => 'boolean',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
    ];

    protected ?array $permissionCache = null;

    /* Relaciones */
    public function institution() { return $this->belongsTo(Institution::class); }
    public function campus() { return $this->belongsTo(Campus::class); }
    public function roles() { return $this->belongsToMany(Role::class); }
    public function teacher() { return $this->hasOne(Teacher::class)->withoutGlobalScopes(); }
    public function student() { return $this->hasOne(Student::class)->withoutGlobalScopes(); }
    public function guardian() { return $this->hasOne(Guardian::class)->withoutGlobalScopes(); }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->name} {$this->paternal_surname} {$this->maternal_surname}");
    }

    /* Roles y permisos: un usuario puede tener varios roles y se suman sus permisos. */
    public function roleSlugs(): array
    {
        return $this->roles->pluck('slug')->all();
    }

    public function hasRole(string ...$slugs): bool
    {
        return (bool) array_intersect($slugs, $this->roleSlugs());
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(Role::SUPERADMIN);
    }

    public function permissionSlugs(): array
    {
        if ($this->permissionCache !== null) {
            return $this->permissionCache;
        }
        if ($this->isSuperAdmin()) {
            return $this->permissionCache = Permission::pluck('slug')->all();
        }
        return $this->permissionCache = Permission::whereHas('roles', fn ($q) => $q->whereIn('roles.id', $this->roles->pluck('id')))
            ->pluck('slug')->all();
    }

    public function hasPermission(string $slug): bool
    {
        return in_array($slug, $this->permissionSlugs(), true);
    }

    /** Ruta de inicio según el rol principal. */
    public function homeRoute(): string
    {
        return match (true) {
            $this->isSuperAdmin() && ! session('tenant_id') => 'superadmin.instituciones.index',
            $this->hasPermission('dashboard.ver') => 'dashboard',
            $this->hasPermission('docencia.usar') => 'docente.inicio',
            $this->hasPermission('portal.alumno'), $this->hasPermission('portal.tutor') => 'portal.inicio',
            default => 'perfil',
        };
    }

    public function auditLabel(): string
    {
        return 'Usuario ' . $this->email;
    }
}
