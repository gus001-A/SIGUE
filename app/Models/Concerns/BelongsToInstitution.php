<?php

namespace App\Models\Concerns;

use App\Models\Institution;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Builder;

/**
 * Separación de datos por institución (regla 8: la información de una
 * institución nunca es visible para otra).
 */
trait BelongsToInstitution
{
    public static function bootBelongsToInstitution(): void
    {
        static::addGlobalScope('institution', function (Builder $q) {
            if ($id = Tenant::id()) {
                $q->where($q->getModel()->getTable() . '.institution_id', $id);
            }
        });

        static::creating(function ($model) {
            if (empty($model->institution_id) && Tenant::id()) {
                $model->institution_id = Tenant::id();
            }
        });
    }

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }
}
