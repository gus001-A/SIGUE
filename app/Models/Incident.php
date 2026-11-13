<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToInstitution;
use App\Models\Concerns\ScopedToCampus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Incident extends Model
{
    use BelongsToInstitution, ScopedToCampus, Auditable, SoftDeletes;

    protected $guarded = ['id'];
    protected static string $campusThrough = 'student';
    protected $casts = ['date' => 'date', 'notify_guardian' => 'boolean'];

    public const CATEGORIES = [
        'conducta_positiva' => 'Conducta positiva',
        'conducta_negativa' => 'Conducta negativa',
        'retardo' => 'Retardo',
        'incumplimiento' => 'Incumplimiento de actividades',
        'participacion' => 'Participación destacada',
        'observacion' => 'Observación',
        'emocional' => 'Seguimiento emocional',
    ];
    public const PRIORITIES = ['baja' => 'Baja', 'media' => 'Media', 'alta' => 'Alta'];
    public const STATUSES = ['abierta' => 'Abierta', 'seguimiento' => 'En seguimiento', 'cerrada' => 'Cerrada'];

    public function student() { return $this->belongsTo(Student::class); }
    public function teacher() { return $this->belongsTo(Teacher::class); }
    public function groupSubject() { return $this->belongsTo(GroupSubject::class); }
    public function reporter() { return $this->belongsTo(User::class, 'reported_by'); }

    /** Liga a la evidencia (archivo privado) o null si no tiene. */
    public function evidenceUrl(): ?string
    {
        return $this->evidence_path ? route('archivos.incidencia', $this) : null;
    }

    /** Nombre legible de la evidencia (con extensión para saber si es imagen o PDF). */
    public function evidenceName(): ?string
    {
        return $this->evidence_path ? 'evidencia-' . $this->id . '.' . pathinfo($this->evidence_path, PATHINFO_EXTENSION) : null;
    }

    public function isPositive(): bool
    {
        return in_array($this->category, ['conducta_positiva', 'participacion'], true);
    }
}
