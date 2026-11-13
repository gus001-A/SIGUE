<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitution;
use App\Models\Concerns\ScopedToCampus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Una asignatura impartida en un grupo por un docente ("clase"). */
class GroupSubject extends Model
{
    use BelongsToInstitution, ScopedToCampus, SoftDeletes;

    protected $guarded = ['id'];
    protected static string $campusThrough = 'group';

    public function group() { return $this->belongsTo(Group::class); }
    public function subject() { return $this->belongsTo(Subject::class); }
    public function teacher() { return $this->belongsTo(Teacher::class); }
    public function schedules() { return $this->hasMany(Schedule::class)->orderBy('day_of_week')->orderBy('starts_at'); }
    public function assignments() { return $this->hasMany(Assignment::class); }
    public function criteria() { return $this->hasMany(EvaluationCriterion::class); }

    public function label(): string
    {
        return ($this->subject?->name ?? 'Clase') . ' · ' . ($this->group?->label ?? '');
    }
}
