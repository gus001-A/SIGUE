<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToInstitution;
use App\Models\Concerns\ScopedToCampus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Adeudo / cargo al alumno (colegiatura, inscripción, cooperación, servicio). */
class Charge extends Model
{
    use BelongsToInstitution, ScopedToCampus, Auditable, SoftDeletes;

    protected $guarded = ['id'];
    protected static string $campusThrough = 'student';
    protected $casts = ['due_on' => 'date', 'amount' => 'float', 'discount' => 'float'];

    public const TYPES = ['colegiatura' => 'Colegiatura', 'inscripcion' => 'Inscripción', 'cooperacion' => 'Cooperación', 'servicio' => 'Servicio', 'otro' => 'Otro'];
    public const STATUSES = ['pendiente' => 'Pendiente', 'parcial' => 'Pago parcial', 'pagado' => 'Pagado', 'cancelado' => 'Cancelado'];

    public function student() { return $this->belongsTo(Student::class); }
    public function scholarship() { return $this->belongsTo(Scholarship::class); }
    public function payments() { return $this->hasMany(Payment::class)->where('status', 'aplicado'); }

    public function total(): float
    {
        return round(max(0, $this->amount - $this->discount), 2);
    }

    public function paid(): float
    {
        return round((float) $this->payments()->sum('amount'), 2);
    }

    public function balance(): float
    {
        return round(max(0, $this->total() - $this->paid()), 2);
    }

    /** Recalcula el estado según los pagos aplicados. */
    public function refreshStatus(): void
    {
        if ($this->status === 'cancelado') {
            return;
        }
        $paid = $this->paid();
        $this->status = $paid <= 0 ? 'pendiente' : ($paid + 0.009 >= $this->total() ? 'pagado' : 'parcial');
        $this->save();
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, ['pendiente', 'parcial'], true) && $this->due_on && $this->due_on->isPast();
    }
}
