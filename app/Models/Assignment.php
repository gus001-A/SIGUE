<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitution;
use App\Models\Concerns\HasFiles;
use App\Models\Concerns\ScopedToCampus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Tarea, proyecto o actividad (Classroom). */
class Assignment extends Model
{
    use BelongsToInstitution, HasFiles, ScopedToCampus, SoftDeletes;

    protected $guarded = ['id'];
    protected static string $campusThrough = 'groupSubject';
    protected $casts = ['due_at' => 'datetime', 'max_score' => 'float', 'is_published' => 'boolean', 'rubric' => 'array', 'links' => 'array'];

    public const TYPES = ['tarea' => 'Tarea', 'proyecto' => 'Proyecto', 'actividad' => 'Actividad'];

    public function groupSubject() { return $this->belongsTo(GroupSubject::class); }
    public function period() { return $this->belongsTo(Period::class); }
    public function topic() { return $this->belongsTo(ClassTopic::class, 'topic_id'); }
    public function criterion() { return $this->belongsTo(EvaluationCriterion::class, 'evaluation_criterion_id'); }
    public function submissions() { return $this->hasMany(Submission::class); }
}
