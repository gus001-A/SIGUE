<?php

namespace App\Models\Concerns;

use App\Models\Campus;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Builder;

/**
 * Registros que pertenecen directamente a un plantel (alumnos, docentes, grupos).
 * Con un plantel activo solo se ven y crean registros de ese plantel.
 */
trait BelongsToCampus
{
    public static function bootBelongsToCampus(): void
    {
        static::addGlobalScope('campus', function (Builder $q) {
            if ($id = Tenant::campusId()) {
                $q->where($q->getModel()->getTable() . '.campus_id', $id);
            }
        });

        static::creating(function ($model) {
            if (Tenant::campusId()) {
                $model->campus_id = Tenant::campusId();
            }
        });

        static::updating(function ($model) {
            // No se puede mover un registro a otro plantel desde un plantel distinto.
            if (Tenant::campusId() && $model->isDirty('campus_id')) {
                $model->campus_id = Tenant::campusId();
            }
        });
    }

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }
}
