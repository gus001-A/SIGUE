<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToInstitution;
use App\Models\Concerns\ScopedToCampus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Criterio de evaluación ponderado de una clase en un periodo. */
class EvaluationCriterion extends Model
{
    use BelongsToInstitution, ScopedToCampus, Auditable, SoftDeletes;

    protected $table = 'evaluation_criteria';
    protected $guarded = ['id'];
    protected static string $campusThrough = 'groupSubject';
    protected $casts = ['weight' => 'float'];

    public const TYPES = [
        'tareas' => 'Tareas', 'examenes' => 'Exámenes', 'proyectos' => 'Proyectos',
        'participacion' => 'Participación', 'asistencia' => 'Asistencia', 'puntualidad' => 'Puntualidad',
        'actividades' => 'Actividades', 'otro' => 'Otro',
    ];

    /** manual = el docente captura; tareas = promedio de entregas ligadas; asistencia = % de asistencia. */
    public const SOURCES = ['manual' => 'Captura manual', 'tareas' => 'Promedio de tareas ligadas', 'asistencia' => 'Porcentaje de asistencia'];

    public function groupSubject() { return $this->belongsTo(GroupSubject::class); }
    public function period() { return $this->belongsTo(Period::class); }
}
