<?php

namespace App\Models\Concerns;

use App\Support\Tenant;
use Illuminate\Database\Eloquent\Builder;

/**
 * Registros que pertenecen a un plantel a través de otra relación
 * (p. ej. un pago a través del alumno, un horario a través del grupo).
 * El modelo define: protected static string $campusThrough = 'student';
 * Si el registro puede no tener esa relación: protected static bool $campusNullable = true;
 */
trait ScopedToCampus
{
    public static function bootScopedToCampus(): void
    {
        static::addGlobalScope('campus', function (Builder $q) {
            if (! Tenant::campusId()) {
                return;
            }
            $through = static::$campusThrough;
            if (property_exists(static::class, 'campusNullable') && static::$campusNullable) {
                $fk = $q->getModel()->{$through}()->getForeignKeyName();
                $q->where(fn ($w) => $w->whereNull($q->getModel()->getTable() . '.' . $fk)->orWhereHas($through));
            } else {
                $q->whereHas($through);
            }
        });
    }
}
