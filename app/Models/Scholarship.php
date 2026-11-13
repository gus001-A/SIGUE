<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToInstitution;
use App\Models\Concerns\ScopedToCampus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Scholarship extends Model
{
    use BelongsToInstitution, ScopedToCampus, Auditable, SoftDeletes;

    protected $guarded = ['id'];
    protected static string $campusThrough = 'student';
    protected static bool $campusNullable = true;
    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date', 'percentage' => 'float', 'amount' => 'float'];

    public const TYPES = ['academica' => 'Académica', 'deportiva' => 'Deportiva', 'socioeconomica' => 'Socioeconómica', 'convenio' => 'Convenio', 'otra' => 'Otra'];
    public const STATUSES = ['activa' => 'Activa', 'suspendida' => 'Suspendida', 'vencida' => 'Vencida'];

    public function student() { return $this->belongsTo(Student::class); }

    public function isActive(): bool
    {
        return $this->status === 'activa'
            && (! $this->ends_on || $this->ends_on->copy()->endOfDay()->isFuture())
            && (! $this->starts_on || $this->starts_on->isPast());
    }

    /** Descuento que aplica sobre un importe. */
    public function discountFor(float $amount): float
    {
        if ($this->percentage) {
            return round($amount * $this->percentage / 100, 2);
        }
        return min($amount, (float) $this->amount);
    }
}
