<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Expediente de salud escolar (dato sensible: acceso con permiso alumnos.salud). */
class StudentHealth extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'student_health';
    protected $guarded = ['id'];
    protected $casts = ['consent_at' => 'datetime'];

    public function student() { return $this->belongsTo(Student::class)->withoutGlobalScopes(); }

    public function getInstitutionIdAttribute()
    {
        return $this->student?->institution_id;
    }

    public function auditLabel(): string { return 'Salud del alumno #' . $this->student_id; }
}
