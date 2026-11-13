<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Charge;
use App\Models\Enrollment;
use App\Models\GradeRecord;
use App\Models\Group;
use App\Models\Incident;
use App\Models\Payment;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TutoringSession;
use App\Support\Options;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Dashboard institucional (sección 11), filtrable por plantel, ciclo, grado y grupo. */
class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $year = $request->filled('school_year_id') ? SchoolYear::find($request->school_year_id) : SchoolYear::current();
        $f = [
            'campus_id' => null, // el plantel lo define el selector global
            'school_year_id' => $year?->id,
            'grade_id' => $request->integer('grade_id') ?: null,
            'group_id' => $request->integer('group_id') ?: null,
        ];

        // Grupos dentro del filtro
        $groupIds = Group::where('school_year_id', $f['school_year_id'])
            ->when($f['campus_id'], fn ($q, $v) => $q->where('campus_id', $v))
            ->when($f['grade_id'], fn ($q, $v) => $q->where('grade_id', $v))
            ->when($f['group_id'], fn ($q, $v) => $q->whereKey($v))
            ->pluck('id');

        $enrollments = Enrollment::whereIn('group_id', $groupIds);
        $studentIds = (clone $enrollments)->where('status', 'autorizada')->pluck('student_id')->unique();

        $today = Carbon::today();
        $attToday = Attendance::whereDate('date', $today)->whereIn('student_id', $studentIds)->pluck('status');
        $attRate = $attToday->count() ? round($attToday->filter(fn ($s) => in_array($s, ['presente', 'retardo', 'justificada']))->count() / $attToday->count() * 100, 1) : null;

        // Asistencia de los últimos 10 días hábiles
        $trend = collect();
        for ($d = $today->copy()->subDays(14); $d->lte($today); $d->addDay()) {
            if (! $d->isWeekday()) {
                continue;
            }
            $rows = Attendance::whereDate('date', $d)->whereIn('student_id', $studentIds)->pluck('status');
            $trend->push([
                'label' => $d->isoFormat('ddd D'),
                'value' => $rows->count() ? round($rows->filter(fn ($s) => $s !== 'falta')->count() / $rows->count() * 100, 1) : null,
            ]);
        }

        // Promedio general: calificaciones capturadas del ciclo (criterios manuales + finales publicadas)
        $avg = GradeRecord::whereIn('student_id', $studentIds)
            ->whereHas('period', fn ($q) => $q->where('school_year_id', $f['school_year_id']))
            ->whereNotNull('score')->avg('score');

        $charges = Charge::whereIn('student_id', $studentIds)->whereIn('status', ['pendiente', 'parcial']);
        $debt = (clone $charges)->get()->sum(fn ($c) => $c->balance());
        $overdue = (clone $charges)->whereDate('due_on', '<', $today)->count();

        $monthIncome = Payment::where('status', 'aplicado')->whereIn('student_id', $studentIds)
            ->whereBetween('paid_at', [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()])->sum('amount');

        $income = collect(range(5, 0))->map(function ($i) use ($today, $studentIds) {
            $m = $today->copy()->subMonths($i);
            return [
                'label' => ucfirst($m->isoFormat('MMM')),
                'value' => round((float) Payment::where('status', 'aplicado')->whereIn('student_id', $studentIds)
                    ->whereBetween('paid_at', [$m->copy()->startOfMonth(), $m->copy()->endOfMonth()])->sum('amount'), 2),
            ];
        });

        $incidents = Incident::whereIn('student_id', $studentIds)->where('date', '>=', $today->copy()->subDays(30));

        // ---------- Datos de las gráficas de barras ----------
        // 1) Asistencia por día (últimos 10 días hábiles), apilada por estado
        $days = collect();
        for ($d = $today->copy(); $days->count() < 10; $d->subDay()) {
            if ($d->isWeekday()) {
                $days->prepend($d->copy());
            }
        }
        $attRows = Attendance::whereIn('student_id', $studentIds)->whereBetween('date', [$days->first()->toDateString(), $today->toDateString()])
            ->selectRaw('DATE(date) as d, status, COUNT(*) as n')->groupBy('d', 'status')->get()->groupBy('d');
        $attendanceDaily = [
            'labels' => $days->map(fn ($d) => ucfirst($d->isoFormat('ddd D MMM')))->values(),
            'today' => $days->count() - 1,
            'series' => collect(['presente' => 'Asistencias', 'retardo' => 'Retardos', 'justificada' => 'Justificadas', 'falta' => 'Faltas'])
                ->map(fn ($label, $st) => ['key' => $st, 'name' => $label,
                    'data' => $days->map(fn ($d) => (int) optional(($attRows[$d->toDateString()] ?? collect())->firstWhere('status', $st))->n)->values()])->values(),
        ];

        // 2) Ingresos cobrados vs. cargos generados (6 meses)
        $months = collect(range(5, 0))->map(fn ($i) => $today->copy()->subMonths($i)->startOfMonth());
        $financeMonthly = [
            'labels' => $months->map(fn ($m) => ucfirst($m->isoFormat('MMM YY')))->values(),
            'charged' => $months->map(fn ($m) => round((float) Charge::whereIn('student_id', $studentIds)->where('status', '!=', 'cancelado')
                ->whereBetween('due_on', [$m->toDateString(), $m->copy()->endOfMonth()->toDateString()])->get()->sum(fn ($c) => $c->total()), 2))->values(),
            'collected' => $income->pluck('value')->values(),
        ];

        // 3) Promedio por grupo (calificaciones finales y de criterios del ciclo)
        $groupsInfo = Group::with('grade:id,name,order')->whereIn('id', $groupIds)->get()
            ->sortBy(fn ($g) => sprintf('%03d-%s', $g->grade?->order, $g->name))->values();
        $avgByGroup = $groupsInfo->map(function ($g) use ($f) {
            $ids = Enrollment::where('group_id', $g->id)->where('status', 'autorizada')->pluck('student_id');
            $a = GradeRecord::whereIn('student_id', $ids)->whereNull('evaluation_criterion_id')
                ->whereHas('period', fn ($q) => $q->where('school_year_id', $f['school_year_id']))->whereNotNull('score')->avg('score')
                ?? GradeRecord::whereIn('student_id', $ids)->whereHas('period', fn ($q) => $q->where('school_year_id', $f['school_year_id']))->whereNotNull('score')->avg('score');
            return ['label' => $g->label, 'avg' => $a ? round($a, 2) : null,
                'enrolled' => $ids->count(), 'capacity' => $g->capacity];
        });

        // 4) Incidencias por categoría (30 días)
        $byCat = (clone $incidents)->selectRaw('category, COUNT(*) as n')->groupBy('category')->pluck('n', 'category');
        $incidentsByCategory = collect(Incident::CATEGORIES)->map(fn ($l, $k) => ['key' => $k, 'label' => $l, 'value' => (int) ($byCat[$k] ?? 0),
            'positive' => in_array($k, ['conducta_positiva', 'participacion'], true)])->sortByDesc('value')->values();

        $passing = (float) (\App\Support\Tenant::institution()?->settings['passing_score'] ?? 6);

        return Inertia::render('Dashboard', [
            'filters' => $f,
            'options' => [
                'schoolYears' => Options::schoolYears(),
                'grades' => Options::grades(),
                // AQUÍ ESTÁ LA CORRECCIÓN: Le pasamos el $f['grade_id'] a la función de opciones
                'groups' => Options::groups($f['school_year_id'], $f['grade_id']), 
            ],
            'kpis' => [
                'enrolled' => $studentIds->count(),
                'active' => Student::where('status', 'activo')->when($f['campus_id'], fn ($q, $v) => $q->where('campus_id', $v))->count(),
                'teachers' => Teacher::where('status', 'activo')->when($f['campus_id'], fn ($q, $v) => $q->where('campus_id', $v))->count(),
                'groups' => $groupIds->count(),
                'attendance_today' => $attRate,
                'average' => $avg ? round($avg, 2) : null,
                'incidents' => (clone $incidents)->count(),
                'incidents_open' => (clone $incidents)->where('status', '!=', 'cerrada')->count(),
                'debt' => round($debt, 2),
                'overdue' => $overdue,
                'income_month' => round((float) $monthIncome, 2),
                'pending_enrollments' => Enrollment::where('status', 'pendiente')->when($groupIds->isNotEmpty(), fn ($q) => $q->whereIn('group_id', $groupIds))->count(),
            ],
            'attendanceTrend' => $trend->values(),
            'charts' => [
                'attendanceDaily' => $attendanceDaily,
                'finance' => $financeMonthly,
                'groups' => $avgByGroup->values(),
                'incidents' => $incidentsByCategory,
                'passing' => $passing,
            ],
            'incomeTrend' => $income->values(),
            'recentIncidents' => Incident::with('student:id,name,paternal_surname,maternal_surname')
                ->whereIn('student_id', $studentIds)->latest('date')->limit(6)->get()
                ->map(fn ($i) => [
                    'id' => $i->id, 'student' => $i->student?->full_name, 'student_id' => $i->student_id,
                    'category' => Incident::CATEGORIES[$i->category] ?? $i->category, 'positive' => $i->isPositive(),
                    'priority' => $i->priority, 'date' => $i->date?->format('d/m/Y'),
                ]),
            'followUps' => TutoringSession::with('student:id,name,paternal_surname')->where('status', 'abierta')
                ->whereDate('follow_up_on', '<=', $today->copy()->addDays(7))->orderBy('follow_up_on')->limit(5)->get()
                ->map(fn ($t) => ['id' => $t->id, 'student' => $t->student?->full_name, 'topic' => $t->topic, 'date' => $t->follow_up_on?->format('d/m/Y')]),
        ]);
    }
}