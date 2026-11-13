<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCampus;
use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Teacher extends Model
{
    use BelongsToInstitution, BelongsToCampus, SoftDeletes, Auditable;

    protected $guarded = ['id'];
    protected $casts = ['hired_on' => 'date'];
    protected $appends = ['full_name', 'title', 'display_name'];

    /** Grado de estudios => [etiqueta, título masculino, título femenino, título sin especificar]. */
    public const DEGREES = [
        'bachillerato' => ['Bachillerato', '', '', ''],
        'tecnico' => ['Técnico superior universitario', 'T.S.U.', 'T.S.U.', 'T.S.U.'],
        'normal' => ['Escuela Normal', 'Profr.', 'Profra.', 'Prof.'],
        'licenciatura' => ['Licenciatura', 'Lic.', 'Lic.', 'Lic.'],
        'ingenieria' => ['Ingeniería', 'Ing.', 'Ing.', 'Ing.'],
        'especialidad' => ['Especialidad', 'Esp.', 'Esp.', 'Esp.'],
        'maestria' => ['Maestría', 'Mtro.', 'Mtra.', 'Mtro(a).'],
        'doctorado' => ['Doctorado', 'Dr.', 'Dra.', 'Dr(a).'],
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function classes() { return $this->hasMany(GroupSubject::class); }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->name} {$this->paternal_surname} {$this->maternal_surname}");
    }

    /** Título según el grado y el sexo: Dr., Dra., Mtro., Lic.… */
    public function getTitleAttribute(): string
    {
        $d = self::DEGREES[$this->degree] ?? null;
        if (! $d) return '';
        return $this->gender === 'M' ? $d[1] : ($this->gender === 'F' ? $d[2] : $d[3]);
    }

    /** Nombre con título, p. ej. "Dr. Juan Pérez López". */
    public function getDisplayNameAttribute(): string
    {
        return trim($this->title . ' ' . $this->full_name);
    }

    public function auditLabel(): string { return 'Docente ' . $this->full_name; }
}
