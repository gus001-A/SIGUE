<?php

namespace App\Support;

use App\Models\Campus;
use App\Models\DocumentType;
use App\Models\Grade;
use App\Models\Period;
use App\Models\SchoolYear;
use App\Models\Subject;

/**
 * Definición declarativa de los catálogos configurables (MOD-CON-001).
 * Cada catálogo se administra con la misma pantalla genérica.
 *
 * Campos: [name, label, type(text|number|date|switch|select|textarea), rules, extra]
 */
class Catalogs
{
    public static function all(): array
    {
        return [
            'planteles' => [
                'model' => Campus::class, 'title' => 'Planteles', 'singular' => 'plantel', 'icon' => 'bank',
                'subtitle' => 'Sedes de la institución (administración multisede)',
                'order' => ['name', 'asc'], 'search' => ['name', 'code'],
                'fields' => [
                    ['name', 'Nombre', 'text', 'required|string|max:150'],
                    ['code', 'Clave', 'text', 'nullable|string|max:30'],
                    ['phone', 'Teléfono', 'text', 'nullable|digits:10', ['placeholder' => '10 dígitos', 'digits' => 10]],
                    ['postal_code', 'Código postal', 'address', 'nullable|string|max:10'],
                    ['state', 'Estado', 'address', 'nullable|string|max:60'],
                    ['municipality', 'Municipio', 'address', 'nullable|string|max:120'],
                    ['colony', 'Colonia', 'address', 'nullable|string|max:120'],
                    ['address', 'Calle y número', 'address', 'nullable|string|max:255'],
                    ['is_active', 'Activo', 'switch', 'boolean', ['default' => true]],
                ],
                'columns' => ['name', 'code', 'address', 'phone', 'is_active'],
                'guard' => fn ($m) => \App\Models\Group::where('campus_id', $m->id)->exists() ? 'El plantel tiene grupos registrados.' : null,
            ],
            'ciclos' => [
                'model' => SchoolYear::class, 'title' => 'Ciclos escolares', 'singular' => 'ciclo escolar', 'icon' => 'calendar',
                'subtitle' => 'Cada institución tiene su propio calendario',
                'order' => ['starts_on', 'desc'], 'search' => ['name'],
                'fields' => [
                    ['name', 'Nombre', 'text', 'required|string|max:40', ['placeholder' => '2026-2027']],
                    ['starts_on', 'Inicio', 'date', 'required|date'],
                    ['ends_on', 'Fin', 'date', 'required|date|after:starts_on'],
                    ['status', 'Estado', 'select', 'required|in:planeacion,abierto,cerrado', ['options' => ['planeacion' => 'Planeación', 'abierto' => 'Abierto', 'cerrado' => 'Cerrado'], 'default' => 'abierto']],
                    ['is_current', 'Ciclo actual', 'switch', 'boolean'],
                ],
                'columns' => ['name', 'starts_on', 'ends_on', 'status', 'is_current'],
                'after' => function ($m) {
                    if ($m->is_current) {
                        SchoolYear::whereKeyNot($m->id)->update(['is_current' => false]);
                    }
                },
                'guard' => fn ($m) => $m->groups()->exists() ? 'El ciclo tiene grupos registrados.' : null,
            ],
            'periodos' => [
                'model' => Period::class, 'title' => 'Periodos de evaluación', 'singular' => 'periodo', 'icon' => 'schedule',
                'subtitle' => 'Parciales, bimestres o trimestres de cada ciclo',
                'order' => ['order', 'asc'], 'search' => ['name'],
                'fields' => [
                    ['school_year_id', 'Ciclo escolar', 'select', 'required|exists:school_years,id', ['source' => 'schoolYears']],
                    ['name', 'Nombre', 'text', 'required|string|max:60', ['placeholder' => 'Trimestre 1']],
                    ['order', 'Orden', 'number', 'required|integer|min:1|max:20', ['default' => 1]],
                    ['starts_on', 'Inicio', 'date', 'nullable|date'],
                    ['ends_on', 'Fin', 'date', 'nullable|date|after_or_equal:starts_on'],
                    ['is_closed', 'Cerrado (no admite cambios)', 'switch', 'boolean'],
                ],
                'columns' => ['school_year_id', 'name', 'order', 'starts_on', 'ends_on', 'is_closed'],
                'filter' => 'school_year_id',
            ],
            'grados' => [
                'model' => Grade::class, 'title' => 'Grados', 'singular' => 'grado', 'icon' => 'rise',
                'subtitle' => 'Grados o semestres por nivel educativo',
                'order' => ['order', 'asc'], 'search' => ['name', 'level'],
                'fields' => [
                    ['name', 'Grado', 'text', 'required|string|max:60', ['placeholder' => '1°']],
                    ['level', 'Nivel', 'select', 'nullable|string|max:40', ['options' => ['Preescolar' => 'Preescolar', 'Primaria' => 'Primaria', 'Secundaria' => 'Secundaria', 'Bachillerato' => 'Bachillerato', 'Licenciatura' => 'Licenciatura', 'Posgrado' => 'Posgrado']]],
                    ['order', 'Orden', 'number', 'required|integer|min:1|max:30', ['default' => 1]],
                ],
                'columns' => ['name', 'level', 'order'],
                'guard' => fn ($m) => \App\Models\Group::where('grade_id', $m->id)->exists() ? 'El grado tiene grupos registrados.' : null,
            ],
            'asignaturas' => [
                'model' => Subject::class, 'title' => 'Asignaturas', 'singular' => 'asignatura', 'icon' => 'book',
                'subtitle' => 'Plan de estudios por grado',
                'order' => ['name', 'asc'], 'search' => ['name', 'code'],
                'fields' => [
                    ['name', 'Nombre', 'text', 'required|string|max:150'],
                    ['code', 'Clave', 'text', 'nullable|string|max:30'],
                    ['grade_id', 'Grado', 'select', 'nullable|exists:grades,id', ['source' => 'grades']],
                    ['hours_per_week', 'Horas por semana', 'number', 'nullable|integer|min:0|max:60'],
                ],
                'columns' => ['name', 'code', 'grade_id', 'hours_per_week'],
                'filter' => 'grade_id',
                'guard' => fn ($m) => \App\Models\GroupSubject::where('subject_id', $m->id)->exists() ? 'La asignatura está asignada a grupos.' : null,
            ],
            'tipos-documento' => [
                'model' => DocumentType::class, 'title' => 'Tipos de documento', 'singular' => 'tipo de documento', 'icon' => 'file',
                'subtitle' => 'Documentos que integran el expediente del alumno',
                'order' => ['name', 'asc'], 'search' => ['name'],
                'fields' => [
                    ['name', 'Nombre', 'text', 'required|string|max:150'],
                    ['is_required', 'Obligatorio', 'switch', 'boolean', ['default' => true]],
                ],
                'columns' => ['name', 'is_required'],
            ],
        ];
    }

    public static function get(string $key): array
    {
        $all = static::all();
        abort_unless(isset($all[$key]), 404);
        return $all[$key] + ['key' => $key];
    }

    /** Opciones de selects referenciadas por 'source'. */
    public static function sources(): array
    {
        return [
            'schoolYears' => Options::schoolYears(),
            'grades' => Options::grades(),
        ];
    }
}
