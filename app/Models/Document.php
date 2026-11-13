<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToInstitution;
use App\Models\Concerns\ScopedToCampus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Documento del expediente del alumno (almacenado en disco privado). */
class Document extends Model
{
    use BelongsToInstitution, ScopedToCampus, Auditable, SoftDeletes;

    protected $guarded = ['id'];
    protected static string $campusThrough = 'student';
    protected $casts = ['reviewed_at' => 'datetime'];

    public const STATUSES = ['pendiente' => 'Pendiente', 'validado' => 'Validado', 'rechazado' => 'Rechazado', 'correccion' => 'Corrección solicitada'];

    public function student() { return $this->belongsTo(Student::class); }
    public function type() { return $this->belongsTo(DocumentType::class, 'document_type_id'); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }

    public function toView(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type?->name,
            'document_type_id' => $this->document_type_id,
            'original_name' => $this->original_name,
            'size' => $this->size,
            'status' => $this->status,
            'review_notes' => $this->review_notes,
            'reviewed_at' => $this->reviewed_at?->format('d/m/Y H:i'),
            'created_at' => $this->created_at?->format('d/m/Y H:i'),
            'url' => route('archivos.documento', $this),
        ];
    }

    public function auditLabel(): string { return 'Documento ' . $this->title . ' (alumno #' . $this->student_id . ')'; }
}
