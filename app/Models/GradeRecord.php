<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToInstitution;
use App\Models\Concerns\ScopedToCampus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Calificación de un alumno: por criterio (evaluation_criterion_id) o final
 * del periodo (evaluation_criterion_id = null).
 */
class GradeRecord extends Model
{
    use BelongsToInstitution, ScopedToCampus, Auditable, SoftDeletes;

    protected $table = 'grades_records';
    protected $guarded = ['id'];
    protected static string $campusThrough = 'student';
    protected $casts = ['score' => 'float', 'is_published' => 'boolean', 'published_at' => 'datetime'];

    public function student() { return $this->belongsTo(Student::class); }
    public function groupSubject() { return $this->belongsTo(GroupSubject::class); }
    public function period() { return $this->belongsTo(Period::class); }
    public function criterion() { return $this->belongsTo(EvaluationCriterion::class, 'evaluation_criterion_id'); }

    public function auditLabel(): string
    {
        return 'Calificación alumno #' . $this->student_id . ' clase #' . $this->group_subject_id . ' periodo #' . $this->period_id;
    }
}
