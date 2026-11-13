<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToInstitution;
use App\Models\Concerns\ScopedToCampus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attendance extends Model
{
    use BelongsToInstitution, ScopedToCampus, Auditable, SoftDeletes;

    protected $table = 'attendance';
    protected $guarded = ['id'];
    protected static string $campusThrough = 'student';
    protected $casts = ['date' => 'date'];

    public const STATUSES = ['presente' => 'Asistencia', 'falta' => 'Falta', 'retardo' => 'Retardo', 'justificada' => 'Justificada'];

    public function student() { return $this->belongsTo(Student::class); }
    public function groupSubject() { return $this->belongsTo(GroupSubject::class); }
    public function recorder() { return $this->belongsTo(User::class, 'recorded_by'); }

    public function auditLabel(): string
    {
        return 'Asistencia alumno #' . $this->student_id . ' · ' . $this->date?->format('d/m/Y');
    }
}
