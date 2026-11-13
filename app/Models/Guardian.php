<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCampus;
use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Padre, madre o tutor. Pertenece a un solo plantel (no se comparte entre planteles), igual que los alumnos. */
class Guardian extends Model
{
    use BelongsToInstitution, BelongsToCampus, SoftDeletes, Auditable;

    protected $guarded = ['id'];
    protected $casts = ['location_consent' => 'boolean', 'identity_verified_at' => 'datetime'];
    protected $appends = ['full_name'];

    public const RELATIONSHIPS = ['padre' => 'Padre', 'madre' => 'Madre', 'tutor' => 'Tutor legal', 'abuelo' => 'Abuelo(a)', 'otro' => 'Otro'];

    public function user() { return $this->belongsTo(User::class); }

    public function students()
    {
        return $this->belongsToMany(Student::class, 'student_guardians')
            ->withPivot(['relationship', 'is_primary', 'can_pickup'])->withTimestamps();
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->name} {$this->paternal_surname} {$this->maternal_surname}");
    }

    public function auditLabel(): string { return 'Tutor ' . $this->full_name; }
}
