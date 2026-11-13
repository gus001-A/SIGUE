<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\Charge;
use App\Models\Enrollment;
use App\Models\GradeRecord;
use App\Models\Group;
use App\Models\GroupSubject;
use App\Models\Incident;
use App\Models\Payment;
use App\Models\Period;
use App\Models\Student;
use App\Models\Teacher;
use Carbon\Carbon;

/**
 * Reportes del módulo MOD-REP-001. Cada reporte devuelve
 * ['title', 'subtitle', 'headings' => [...], 'rows' => [[...]]].
 */
class Reports
{
    public const LIST = [
        'calificaciones' => ['Académicos', 'Calificaciones por grupo', 'Promedio por asignatura y periodo', ['group_id', 'period_id'], 'reportes.ver'],
        'kardex' => ['Académicos', 'Kárdex del alumno', 'Historial de calificaciones finales', ['student_id'], 'reportes.ver'],
        'promedios' => ['Académicos', 'Promedios generales', 'Promedio por alumno del ciclo', ['group_id'], 'reportes.ver'],
        'asistencia' => ['Asistencia', 'Asistencias, faltas y retardos', 'Concentrado por alumno en un rango de fechas', ['group_id', 'from', 'to'], 'reportes.ver'],
        'docentes' => ['Docentes', 'Grupos y carga docente', 'Clases, horas y alumnos por docente', [], 'reportes.ver'],
        'incidencias' => ['Conductual', 'Incidencias y reconocimientos', 'Registro conductual por periodo', ['group_id', 'from', 'to'], 'reportes.ver'],
        'inscripciones' => ['Administrativo', 'Inscripciones y bajas', 'Movimientos de matrícula del ciclo', ['from', 'to'], 'reportes.ver'],
        'pagos' => ['Financiero', 'Pagos recibidos', 'Ingresos por fecha y forma de pago', ['from', 'to'], 'reportes.financieros'],
        'adeudos' => ['Financiero', 'Adeudos pendientes', 'Saldos por alumno y concepto', ['group_id'], 'reportes.financieros'],
        'estadisticas' => ['Institucional', 'Estadísticas generales', 'Indicadores de la institución', [], 'reportes.ver'],
    ];

    public static function build(string $key, array $p): array
    {
        $from = ! empty($p['from']) ? Carbon::parse($p['from']) : today()->startOfMonth();
        $to = ! empty($p['to']) ? Carbon::parse($p['to']) : today();
        $group = ! empty($p['group_id']) ? Group::with('grade')->find($p['group_id']) : null;
        $groupStudents = fn () => $group ? $group->students()->get() : Student::where('status', 'activo')->orderBy('paternal_surname')->get();
        $range = $from->format('d/m/Y') . ' – ' . $to->format('d/m/Y');

        return match ($key) {
            'calificaciones' => static::calificaciones($group, ! empty($p['period_id']) ? Period::find($p['period_id']) : null),

            'kardex' => static::kardex(Student::findOrFail($p['student_id'] ?? 0)),

            'promedios' => [
                'title' => 'Promedios generales', 'subtitle' => $group?->label ?? 'Todos los alumnos activos',
                'headings' => ['Matrícula', 'Alumno', 'Grupo', 'Calificaciones', 'Promedio'],
                'rows' => $groupStudents()->map(function ($s) {
                    $g = GradeRecord::where('student_id', $s->id)->whereNull('evaluation_criterion_id')->where('is_published', true)->pluck('score');
                    return [$s->enrollment_number, $s->listName(), $s->currentEnrollment?->group?->label, $g->count(), $g->count() ? round($g->avg(), 2) : '—'];
                })->sortByDesc(fn ($r) => is_numeric($r[4]) ? $r[4] : -1)->values()->all(),
            ],

            'asistencia' => [
                'title' => 'Asistencias', 'subtitle' => ($group?->label ?? 'Todos los grupos') . ' · ' . $range,
                'headings' => ['Matrícula', 'Alumno', 'Asistencias', 'Faltas', 'Retardos', 'Justificadas', '% asistencia'],
                'rows' => $groupStudents()->map(function ($s) use ($from, $to) {
                    $a = Attendance::where('student_id', $s->id)->whereBetween('date', [$from->toDateString(), $to->toDateString()])->pluck('status');
                    $c = fn ($x) => $a->filter(fn ($v) => $v === $x)->count();
                    return [$s->enrollment_number, $s->listName(), $c('presente'), $c('falta'), $c('retardo'), $c('justificada'),
                        $a->count() ? round(($a->count() - $c('falta')) / $a->count() * 100, 1) . ' %' : '—'];
                })->all(),
            ],

            'docentes' => [
                'title' => 'Grupos y carga docente', 'subtitle' => 'Ciclo actual',
                'headings' => ['No. empleado', 'Docente', 'Especialidad', 'Clases', 'Grupos', 'Horas/semana', 'Alumnos'],
                'rows' => Teacher::where('status', 'activo')->orderBy('paternal_surname')->get()->map(function ($t) {
                    $classes = GroupSubject::with(['group', 'schedules'])->where('teacher_id', $t->id)->get();
                    $hours = $classes->sum(fn ($c) => $c->schedules->sum(fn ($s) => Carbon::parse($s->starts_at)->diffInMinutes(Carbon::parse($s->ends_at)) / 60));
                    $students = Enrollment::whereIn('group_id', $classes->pluck('group_id')->unique())->where('status', 'autorizada')->distinct('student_id')->count('student_id');
                    return [$t->employee_number, $t->full_name, $t->specialty, $classes->count(), $classes->pluck('group_id')->unique()->count(), round($hours, 1), $students];
                })->all(),
            ],

            'incidencias' => [
                'title' => 'Incidencias y reconocimientos', 'subtitle' => ($group?->label ?? 'Todos los grupos') . ' · ' . $range,
                'headings' => ['Fecha', 'Alumno', 'Categoría', 'Prioridad', 'Estado', 'Docente', 'Descripción'],
                'rows' => Incident::with(['student', 'teacher'])->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                    ->when($group, fn ($q) => $q->whereIn('student_id', $group->students()->pluck('students.id')))
                    ->orderBy('date')->get()
                    ->map(fn ($i) => [$i->date->format('d/m/Y'), $i->student?->full_name, Incident::CATEGORIES[$i->category] ?? $i->category,
                        ucfirst($i->priority), Incident::STATUSES[$i->status] ?? $i->status, $i->teacher?->full_name, $i->description])->all(),
            ],

            'inscripciones' => [
                'title' => 'Inscripciones y bajas', 'subtitle' => $range,
                'headings' => ['Fecha', 'Matrícula', 'Alumno', 'Grupo', 'Tipo', 'Estado', 'Motivo de baja'],
                'rows' => Enrollment::with(['student', 'group.grade'])
                    ->where(fn ($q) => $q->whereBetween('enrolled_on', [$from->toDateString(), $to->toDateString()])
                        ->orWhereBetween('dropped_on', [$from->toDateString(), $to->toDateString()]))
                    ->orderBy('enrolled_on')->get()
                    ->map(fn ($e) => [($e->dropped_on ?? $e->enrolled_on)?->format('d/m/Y'), $e->student?->enrollment_number, $e->student?->full_name,
                        $e->group?->label, ucfirst($e->type), \App\Models\Enrollment::STATUSES[$e->status] ?? $e->status, $e->drop_reason])->all(),
            ],

            'pagos' => static::pagos($from, $to, $range),

            'adeudos' => [
                'title' => 'Adeudos pendientes', 'subtitle' => $group?->label ?? 'Toda la institución',
                'headings' => ['Matrícula', 'Alumno', 'Concepto', 'Vencimiento', 'Total', 'Pagado', 'Saldo', 'Estado'],
                'rows' => Charge::with('student')->whereIn('status', ['pendiente', 'parcial'])
                    ->when($group, fn ($q) => $q->whereIn('student_id', $group->students()->pluck('students.id')))
                    ->orderBy('due_on')->get()
                    ->map(fn ($c) => [$c->student?->enrollment_number, $c->student?->full_name, $c->concept, $c->due_on?->format('d/m/Y'),
                        $c->total(), $c->paid(), $c->balance(), $c->isOverdue() ? 'VENCIDO' : ucfirst($c->status)])->all(),
            ],

            'estadisticas' => static::estadisticas(),

            default => abort(404),
        };
    }

    private static function calificaciones(?Group $group, ?Period $period): array
    {
        abort_unless($group && $period, 422, 'Selecciona grupo y periodo.');
        $classes = GroupSubject::with('subject')->where('group_id', $group->id)->get()->sortBy('subject.name')->values();
        $students = $group->students()->get();

        $rows = $students->map(function ($s) use ($classes, $period) {
            $scores = $classes->map(function ($c) use ($s, $period) {
                $final = GradeRecord::where(['group_subject_id' => $c->id, 'period_id' => $period->id, 'student_id' => $s->id])
                    ->whereNull('evaluation_criterion_id')->value('score');
                return $final ?? ((new \App\Services\GradeCalculator($c, $period))->matrix([$s->id])[$s->id]['computed'] ?? null);
            });
            $valid = $scores->filter(fn ($v) => $v !== null);
            return array_merge([$s->enrollment_number, $s->listName()], $scores->map(fn ($v) => $v ?? '—')->all(),
                [$valid->count() ? round($valid->avg(), 2) : '—']);
        });

        return [
            'title' => 'Calificaciones · ' . $period->name, 'subtitle' => 'Grupo ' . $group->label,
            'headings' => array_merge(['Matrícula', 'Alumno'], $classes->map(fn ($c) => $c->subject?->name)->all(), ['Promedio']),
            'rows' => $rows->all(),
        ];
    }

    private static function kardex(Student $s): array
    {
        $rows = GradeRecord::with(['groupSubject.subject', 'groupSubject.group.grade', 'groupSubject.group.schoolYear', 'period'])
            ->where('student_id', $s->id)->whereNull('evaluation_criterion_id')->where('is_published', true)->get()
            ->sortBy(fn ($g) => ($g->groupSubject?->group?->schoolYear?->starts_on?->format('Ymd') ?? '') . $g->period?->order)
            ->map(fn ($g) => [$g->groupSubject?->group?->schoolYear?->name, $g->groupSubject?->group?->label, $g->period?->name,
                $g->groupSubject?->subject?->name, $g->score])->values();

        return [
            'title' => 'Kárdex', 'subtitle' => $s->full_name . ' · Matrícula ' . $s->enrollment_number,
            'headings' => ['Ciclo', 'Grupo', 'Periodo', 'Asignatura', 'Calificación'],
            'rows' => $rows->all(),
            'footer' => 'Promedio general: ' . ($rows->count() ? number_format($rows->avg(4), 2) : '—'),
        ];
    }

    private static function pagos(Carbon $from, Carbon $to, string $range): array
    {
        $payments = Payment::with(['student', 'charge'])->where('status', 'aplicado')
            ->whereBetween('paid_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->orderBy('paid_at')->get();

        return [
            'title' => 'Pagos recibidos', 'subtitle' => $range,
            'headings' => ['Folio', 'Fecha', 'Matrícula', 'Alumno', 'Concepto', 'Forma de pago', 'Importe'],
            'rows' => $payments->map(fn ($p) => [$p->folio, $p->paid_at->format('d/m/Y H:i'), $p->student?->enrollment_number, $p->student?->full_name,
                $p->charge?->concept ?? 'Pago a cuenta', Payment::METHODS[$p->method] ?? $p->method, $p->amount])->all(),
            'footer' => 'Total: $' . number_format($payments->sum('amount'), 2) . ' · ' . $payments->groupBy('method')
                ->map(fn ($g, $m) => (Payment::METHODS[$m] ?? $m) . ' $' . number_format($g->sum('amount'), 2))->implode(' · '),
        ];
    }

    private static function estadisticas(): array
    {
        $att = Attendance::where('date', '>=', today()->subDays(30))->pluck('status');
        $open = Charge::whereIn('status', ['pendiente', 'parcial'])->get();
        $rows = [
            ['Alumnos activos', Student::where('status', 'activo')->count()],
            ['Aspirantes', Student::where('status', 'aspirante')->count()],
            ['Bajas', Student::where('status', 'baja')->count()],
            ['Egresados', Student::whereIn('status', ['egresado', 'titulado'])->count()],
            ['Docentes activos', Teacher::where('status', 'activo')->count()],
            ['Grupos del ciclo actual', Group::where('school_year_id', \App\Models\SchoolYear::current()?->id)->count()],
            ['Asistencia últimos 30 días', $att->count() ? round(($att->count() - $att->filter(fn ($s) => $s === 'falta')->count()) / $att->count() * 100, 1) . ' %' : '—'],
            ['Promedio general publicado', ($avg = GradeRecord::whereNull('evaluation_criterion_id')->where('is_published', true)->avg('score')) ? round($avg, 2) : '—'],
            ['Incidencias últimos 30 días', Incident::where('date', '>=', today()->subDays(30))->count()],
            ['Saldo por cobrar', '$' . number_format($open->sum(fn ($c) => $c->balance()), 2)],
            ['Ingresos del mes', '$' . number_format(Payment::where('status', 'aplicado')->whereBetween('paid_at', [now()->startOfMonth(), now()])->sum('amount'), 2)],
        ];

        return ['title' => 'Estadísticas generales', 'subtitle' => 'Al ' . today()->format('d/m/Y'), 'headings' => ['Indicador', 'Valor'], 'rows' => $rows];
    }
}
