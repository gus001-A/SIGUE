<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToInstitution;
use App\Models\Concerns\ScopedToCampus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use BelongsToInstitution, ScopedToCampus, Auditable, SoftDeletes;

    protected $guarded = ['id'];
    protected static string $campusThrough = 'student';
    protected $casts = ['paid_at' => 'datetime', 'amount' => 'float'];

    public const METHODS = ['efectivo' => 'Efectivo', 'transferencia' => 'Transferencia', 'tarjeta' => 'Tarjeta', 'deposito' => 'Depósito'];

    public function student() { return $this->belongsTo(Student::class); }
    public function charge() { return $this->belongsTo(Charge::class); }
    public function receiver() { return $this->belongsTo(User::class, 'received_by'); }

    /** Siguiente folio consecutivo de la institución: R-000001. */
    public static function nextFolio(): string
    {
        $q = fn () => static::withoutGlobalScope('campus');
        $last = $q()->max('id') ?? 0;
        do {
            $folio = 'R-' . str_pad((string) (++$last), 6, '0', STR_PAD_LEFT);
        } while ($q()->where('folio', $folio)->exists());
        return $folio;
    }

    public function auditLabel(): string { return 'Pago ' . $this->folio; }
}
