<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campus extends Model
{
    use BelongsToInstitution, SoftDeletes;

    protected $guarded = ['id'];
    protected $casts = ['is_active' => 'boolean', 'breaks' => 'array'];

    public const SHIFTS = ['matutino' => 'Matutino', 'vespertino' => 'Vespertino', 'nocturno' => 'Nocturno', 'mixto' => 'Mixto'];

    /** Receso del turno: ['start' => 'HH:MM', 'end' => 'HH:MM'] o null si no está configurado. */
    public function breakFor(?string $shift): ?array
    {
        $b = $this->breaks[$shift ?? ''] ?? null;
        return ($b['start'] ?? null) && ($b['end'] ?? null) ? ['start' => $b['start'], 'end' => $b['end']] : null;
    }
}
