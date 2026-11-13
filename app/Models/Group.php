<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCampus;
use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Group extends Model
{
    use BelongsToInstitution, BelongsToCampus, SoftDeletes;

    protected $guarded = ['id'];
    protected $appends = ['label'];

    public function schoolYear() { return $this->belongsTo(SchoolYear::class); }
    public function grade() { return $this->belongsTo(Grade::class); }
    public function tutor() { return $this->belongsTo(Teacher::class, 'tutor_teacher_id'); }
    public function classes() { return $this->hasMany(GroupSubject::class); }
    public function enrollments() { return $this->hasMany(Enrollment::class); }

    /** Alumnos con inscripción autorizada en el grupo. */
    public function students()
    {
        return $this->belongsToMany(Student::class, 'enrollments')
            ->wherePivot('status', 'autorizada')
            ->orderBy('students.paternal_surname')->orderBy('students.maternal_surname')->orderBy('students.name');
    }

    /** "1° A" */
    public function getLabelAttribute(): string
    {
        $grade = $this->relationLoaded('grade') ? $this->grade : Grade::withoutGlobalScopes()->find($this->grade_id);
        return trim(($grade?->name ?? '') . ' ' . $this->name);
    }
}
