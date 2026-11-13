<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToInstitution;
use App\Models\Concerns\ScopedToCampus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Enrollment extends Model
{
    use BelongsToInstitution, ScopedToCampus, Auditable, SoftDeletes;

    protected $guarded = ['id'];
    protected static string $campusThrough = 'student';
    protected $casts = ['enrolled_on' => 'date', 'dropped_on' => 'date', 'authorized_at' => 'datetime'];

    public const STATUSES = ['pendiente' => 'Pendiente', 'autorizada' => 'Autorizada', 'baja' => 'Baja', 'concluida' => 'Concluida'];

    public function student() { return $this->belongsTo(Student::class); }
    public function group() { return $this->belongsTo(Group::class); }
    public function schoolYear() { return $this->belongsTo(SchoolYear::class); }
    public function authorizer() { return $this->belongsTo(User::class, 'authorized_by'); }

    public function auditLabel(): string { return 'Inscripción #' . $this->id . ' (alumno #' . $this->student_id . ')'; }
}
