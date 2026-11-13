<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCampus;
use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use BelongsToInstitution, BelongsToCampus, SoftDeletes, Auditable;

    protected $guarded = ['id'];
    protected $casts = [
        'birth_date' => 'date',
        'graduated_on' => 'date',
        'photo_publication_consent' => 'boolean',
    ];
    protected $appends = ['full_name'];

    public const STATUSES = [
        'aspirante' => 'Aspirante', 'activo' => 'Activo', 'baja' => 'Baja',
        'egresado' => 'Egresado', 'titulado' => 'Titulado',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function health() { return $this->hasOne(StudentHealth::class); }
    public function enrollments() { return $this->hasMany(Enrollment::class)->latest('id'); }
    public function documents() { return $this->hasMany(Document::class); }
    public function charges() { return $this->hasMany(Charge::class); }
    public function payments() { return $this->hasMany(Payment::class); }
    public function scholarships() { return $this->hasMany(Scholarship::class); }
    public function incidents() { return $this->hasMany(Incident::class); }
    public function attendance() { return $this->hasMany(Attendance::class); }

    public function guardians()
    {
        return $this->belongsToMany(Guardian::class, 'student_guardians')
            ->withPivot(['relationship', 'is_primary', 'can_pickup'])->withTimestamps();
    }

    /** Inscripción autorizada vigente (ciclo actual o la más reciente). */
    public function currentEnrollment()
    {
        return $this->hasOne(Enrollment::class)->where('status', 'autorizada')->latestOfMany();
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->name} {$this->paternal_surname} {$this->maternal_surname}");
    }

    /** "Apellidos, Nombre" para listas de grupo. */
    public function listName(): string
    {
        return trim("{$this->paternal_surname} {$this->maternal_surname}") . ', ' . $this->name;
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? route('archivos.foto', $this) : null;
    }

    /** IDs de usuario de los tutores (para notificaciones). */
    public function guardianUserIds(): array
    {
        return $this->guardians()->whereNotNull('guardians.user_id')->pluck('guardians.user_id')->all();
    }

    public function auditLabel(): string { return 'Alumno ' . $this->full_name; }
}
