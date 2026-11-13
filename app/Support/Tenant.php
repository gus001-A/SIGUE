<?php

namespace App\Support;

use App\Models\Campus;
use App\Models\Institution;

/**
 * Institución y plantel activos de la petición.
 *
 *  - institution_id: separa la información entre instituciones (BelongsToInstitution).
 *  - campus_id: el plantel con el que se está trabajando; los modelos con
 *    BelongsToCampus / ScopedToCampus solo muestran y modifican registros de ese plantel.
 */
class Tenant
{
    protected static ?int $id = null;
    protected static ?Institution $institution = null;
    protected static ?int $campusId = null;
    protected static ?Campus $campus = null;

    public static function set(?int $id): void
    {
        static::$id = $id;
        static::$institution = null;
        static::setCampus(null);
    }

    public static function id(): ?int
    {
        return static::$id;
    }

    public static function institution(): ?Institution
    {
        if (! static::$id) {
            return null;
        }
        return static::$institution ??= Institution::find(static::$id);
    }

    public static function setCampus(?int $campusId): void
    {
        static::$campusId = $campusId;
        static::$campus = null;
    }

    public static function campusId(): ?int
    {
        return static::$campusId;
    }

    public static function campus(): ?Campus
    {
        if (! static::$campusId) {
            return null;
        }
        return static::$campus ??= Campus::withoutGlobalScopes()->find(static::$campusId);
    }

    /** Ejecuta un callback con otra institución (y plantel) activos: seeders, superadmin. */
    public static function run(?int $id, callable $fn, ?int $campusId = null)
    {
        [$prev, $prevCampus] = [static::$id, static::$campusId];
        static::set($id);
        static::setCampus($campusId);
        try {
            return $fn();
        } finally {
            static::set($prev);
            static::setCampus($prevCampus);
        }
    }

    /** Ejecuta sin filtro de plantel (consultas de toda la institución). */
    public static function allCampuses(callable $fn)
    {
        $prev = static::$campusId;
        static::$campusId = null;
        try {
            return $fn();
        } finally {
            static::$campusId = $prev;
        }
    }
}
