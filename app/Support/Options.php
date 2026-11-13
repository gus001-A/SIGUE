<?php

namespace App\Support;

use App\Models\Campus;
use App\Models\DocumentType;
use App\Models\Grade;
use App\Models\Group;
use App\Models\Period;
use App\Models\SchoolYear;
use App\Models\Subject;
use App\Models\Teacher;

/** Opciones para selects (siempre filtradas por la institución activa). */
class Options
{
    public static function campuses(): array
    {
        return Campus::where('is_active', true)->orderBy('name')->get(['id', 'name'])
            ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])->all();
    }

    public static function schoolYears(): array
    {
        return SchoolYear::orderByDesc('starts_on')->get(['id', 'name', 'is_current'])
            ->map(fn ($y) => ['value' => $y->id, 'label' => $y->name . ($y->is_current ? ' (actual)' : '')])->all();
    }

    public static function grades(): array
    {
        return Grade::orderBy('order')->get(['id', 'name', 'level'])
            ->map(fn ($g) => ['value' => $g->id, 'label' => trim($g->name . ' ' . ($g->level ?? ''))])->all();
    }

    public static function groups(?int $schoolYearId = null, ?int $gradeId = null): array
    {
        $yearId = $schoolYearId ?? SchoolYear::current()?->id;
        return Group::with('grade:id,name,order')->when($yearId, fn ($q) => $q->where('school_year_id', $yearId))
            ->when($gradeId, fn ($q) => $q->where('grade_id', $gradeId))
            ->get()->sortBy(fn ($g) => sprintf('%03d-%s', $g->grade?->order, $g->name))
            ->map(fn ($g) => ['value' => $g->id, 'label' => $g->label . ' · ' . ucfirst($g->shift), 'grade_id' => $g->grade_id])
            ->values()->all();
    }

    public static function teachers(): array
    {
        return Teacher::where('status', 'activo')->orderBy('paternal_surname')->get()
            ->map(fn ($t) => ['value' => $t->id, 'label' => $t->display_name])->all();
    }

    public static function subjects(): array
    {
        return Subject::with('grade:id,name')->where('is_active', true)->orderBy('name')->get()
            ->map(fn ($s) => ['value' => $s->id, 'label' => $s->name . ($s->grade ? ' (' . $s->grade->name . ')' : ''), 'grade_id' => $s->grade_id])->all();
    }

    public static function periods(?int $schoolYearId = null): array
    {
        $yearId = $schoolYearId ?? SchoolYear::current()?->id;
        return Period::where('school_year_id', $yearId)->orderBy('order')->get()
            ->map(fn ($p) => ['value' => $p->id, 'label' => $p->name, 'is_closed' => $p->is_closed])->all();
    }

    public static function documentTypes(): array
    {
        return DocumentType::orderBy('name')->get(['id', 'name', 'is_required'])
            ->map(fn ($d) => ['value' => $d->id, 'label' => $d->name, 'required' => $d->is_required])->all();
    }

    /** Convierte un arreglo slug => etiqueta en opciones. */
    public static function from(array $map): array
    {
        return collect($map)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all();
    }
}
