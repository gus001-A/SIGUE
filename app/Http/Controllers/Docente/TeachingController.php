<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Attendance;
use App\Models\EvaluationCriterion;
use App\Models\GroupSubject;
use App\Models\Incident;
use App\Models\Period;
use App\Models\Schedule;
use App\Models\SchoolYear;
use App\Models\Submission;
use App\Models\TutoringSession;
use App\Services\GradeCalculator;
use App\Support\Access;
use App\Support\Options;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Módulo docente (MOD-DOC-001): inicio, clases y vista integral de una clase. */
class TeachingController extends Controller
{
    /** Clases visibles: las del docente; el personal académico puede ver todas. */
    private function myClasses(Request $request)
    {
        $teacher = $request->user()->teacher;
        $year = SchoolYear::current();

        return GroupSubject::with(['group.grade', 'subject:id,name,code', 'schedules', 'teacher:id,name,paternal_surname,maternal_surname'])
            ->whereHas('group', fn ($q) => $q->where('school_year_id', $year?->id))
            ->when($teacher, fn ($q) => $q->where('teacher_id', $teacher->id), fn ($q) => $q->whereRaw('1 = 0'))
            ->get()
            ->sortBy(fn ($c) => sprintf('%03d-%s-%s', $c->group?->grade?->order, $c->group?->name, $c->subject?->name))->values();
    }

    public function inicio(Request $request)
    {
        $classes = $this->myClasses($request);
        $today = Carbon::today();
        $classIds = $classes->pluck('id');

        $rows = Schedule::with(['groupSubject.subject:id,name', 'groupSubject.group.grade'])
            ->whereIn('group_subject_id', $classIds)->where('day_of_week', $today->dayOfWeekIso)->orderBy('starts_at')->get();
        $takenToday = Attendance::whereIn('group_subject_id', $classIds)->whereDate('date', $today)
            ->select('group_subject_id', 'session')->distinct()->get()->map(fn ($a) => $a->group_subject_id . ':' . $a->session)->flip();
        $now = now()->format('H:i');
        $todaySchedule = $rows->map(function ($s) use ($rows, $takenToday, $now) {
            // Número de sesión del día de esa clase (1ª, 2ª…) para el pase de lista
            $session = $rows->where('group_subject_id', $s->group_subject_id)->sortBy('starts_at')->values()->search(fn ($x) => $x->id === $s->id) + 1;
            $start = substr($s->starts_at, 0, 5);
            $end = substr($s->ends_at, 0, 5);
            return [
                'class_id' => $s->group_subject_id, 'subject' => $s->groupSubject?->subject?->name, 'group' => $s->groupSubject?->group?->label,
                'starts_at' => $start, 'ends_at' => $end, 'room' => $s->room, 'session' => $session,
                'taken' => $takenToday->has($s->group_subject_id . ':' . $session),
                // Solo se puede pasar lista desde la hora de inicio
                'state' => $now < $start ? 'upcoming' : ($now < $end ? 'now' : 'past'),
            ];
        })->values();

        $toGrade = Submission::with(['assignment:id,title,group_subject_id', 'student:id,name,paternal_surname'])
            ->whereHas('assignment', fn ($q) => $q->whereIn('group_subject_id', $classIds))
            ->whereNull('score')->latest('submitted_at')->limit(8)->get()
            ->map(fn ($s) => ['id' => $s->id, 'class_id' => $s->assignment->group_subject_id, 'assignment' => $s->assignment->title,
                'student' => $s->student?->full_name, 'date' => $s->submitted_at?->diffForHumans()]);

        $teacher = $request->user()->teacher;

        // Por revisar: entregas sin calificar agrupadas por tarea (como "Para revisar" de Google Classroom)
        $ungraded = Submission::whereHas('assignment', fn ($q) => $q->whereIn('group_subject_id', $classIds))
            ->whereNull('score')->whereNotNull('submitted_at');
        $perClass = (clone $ungraded)->with('assignment:id,group_subject_id')->get()->countBy(fn ($s) => $s->assignment?->group_subject_id);
        $toReview = (clone $ungraded)->with('assignment:id,title,type,due_at,group_subject_id', 'assignment.groupSubject.subject:id,name', 'assignment.groupSubject.group.grade')
            ->get()->groupBy('assignment_id')
            ->map(fn ($rows) => [
                'id' => $rows->first()->assignment_id, 'title' => $rows->first()->assignment?->title, 'type' => $rows->first()->assignment?->type,
                'subject' => $rows->first()->assignment?->groupSubject?->subject?->name, 'group' => $rows->first()->assignment?->groupSubject?->group?->label,
                'count' => $rows->count(), 'last' => $rows->max('submitted_at')?->diffForHumans(), 'last_at' => $rows->max('submitted_at')?->timestamp,
                'due' => $rows->first()->assignment?->due_at?->isoFormat('D MMM'),
            ])->sortByDesc('last_at')->take(6)->values();

        return Inertia::render($this->view($request, 'Docente/Inicio', 'App/Docente/Inicio'), [
            'toReview' => $toReview,
            'teacher' => $teacher?->full_name,
            'classes' => $classes->map(fn ($c) => [
                'id' => $c->id, 'subject' => $c->subject?->name, 'group' => $c->group?->label, 'shift' => $c->group?->shift,
                'students' => $c->group?->students()->count(), 'hours' => $c->schedules->count(),
                'to_grade' => (int) ($perClass[$c->id] ?? 0), 'level' => $c->group?->grade?->level,
            ]),
            'today' => $todaySchedule,
            'toGrade' => $toGrade,
            'pendingSubmissions' => (clone $ungraded)->count(),
            'followUps' => TutoringSession::with('student:id,name,paternal_surname')->where('teacher_id', $teacher?->id)
                ->where('status', 'abierta')->orderBy('follow_up_on')->limit(5)->get()
                ->map(fn ($t) => ['id' => $t->id, 'student' => $t->student?->full_name, 'topic' => $t->topic, 'date' => $t->follow_up_on?->format('d/m/Y'),
                    'overdue' => $t->follow_up_on?->isPast()]),
            'hasTeacher' => (bool) $teacher,
        ]);
    }

    public function clases(Request $request)
    {
        $classes = $this->myClasses($request);

        // Horario semanal del docente
        $schedule = $classes->flatMap(fn ($c) => $c->schedules->map(fn ($s) => [
            'day' => $s->day_of_week, 'starts_at' => substr($s->starts_at, 0, 5), 'ends_at' => substr($s->ends_at, 0, 5),
            'subject' => $c->subject?->name, 'group' => $c->group?->label, 'room' => $s->room, 'class_id' => $c->id,
        ]))->sortBy(['day', 'starts_at'])->values();

        $toGrade = \App\Models\Submission::whereNull('score')->whereNotNull('submitted_at')
            ->whereHas('assignment', fn ($q) => $q->whereIn('group_subject_id', $classes->pluck('id')))
            ->with('assignment:id,group_subject_id')->get()->countBy(fn ($s) => $s->assignment?->group_subject_id);

        return Inertia::render($this->view($request, 'Docente/Clases', 'App/Docente/Clases'), [
            'classes' => $classes->map(fn ($c) => [
                'id' => $c->id, 'subject' => $c->subject?->name, 'code' => $c->subject?->code, 'group' => $c->group?->label,
                'shift' => $c->group?->shift, 'students' => $c->group?->students()->count(),
                'assignments' => $c->assignments()->count(), 'to_grade' => (int) ($toGrade[$c->id] ?? 0),
                'level' => $c->group?->grade?->level,
            ]),
            'schedule' => $schedule,
            'days' => Schedule::DAYS,
        ]);
    }

    /**
     * Abrir una clase: se recuerda como "clase de trabajo" y se va a la sección pedida
     * (compatibilidad con enlaces antiguos ?tab=...).
     */
    public function show(Request $request, GroupSubject $class)
    {
        abort_unless(Access::canManageClass($request->user(), $class), 403);
        $map = ['asistencia' => 'asistencia', 'classroom' => 'classroom', 'evaluacion' => 'evaluacion', 'incidencias' => 'incidencias', 'alumnos' => 'alumnos'];
        $section = $map[$request->query('tab')] ?? session('docente.section', 'asistencia');

        return redirect()->route('docente.clase.' . $section, ['class' => $class->id] + $request->except('tab'));
    }

    public function asistencia(Request $request, GroupSubject $class)
    {
        $data = $this->base($request, $class, 'asistencia');
        $class->loadMissing('schedules');
        $days = $class->schedules->pluck('day_of_week')->unique()->sort()->values();
        $classDays = $days->isNotEmpty() ? $days->all() : [1, 2, 3, 4, 5];
        $sessionsOn = fn (int $dow) => max(1, $class->schedules->where('day_of_week', $dow)->count());

        // Fecha pedida; si no es día de clase (o es futura) se usa el último día de clase.
        $date = Carbon::parse($request->query('date', today()->toDateString()));
        if ($date->isFuture() || ! in_array($date->dayOfWeekIso, $classDays, true)) {
            $requested = $request->has('date') ? $date->copy() : null;
            $date = today();
            while (! in_array($date->dayOfWeekIso, $classDays, true)) {
                $date->subDay();
            }
            if ($requested) {
                session()->now('warning', $requested->isFuture()
                    ? 'No se puede pasar lista de un día futuro.'
                    : 'El ' . $requested->isoFormat('dddd D [de] MMMM') . ' no hay clase de ' . $class->subject?->name . '. Se muestra el último día de clase.');
            }
        }
        $session = min(max(1, (int) $request->query('session', 1)), $sessionsOn($date->dayOfWeekIso));
        $students = collect($data['students']);

        $records = Attendance::with('recorder:id,name,paternal_surname')->where('group_subject_id', $class->id)
            ->whereDate('date', $date)->where('session', $session)->get()->keyBy('student_id');

        // Días de clase recientes (para elegir rápido y ver cuáles faltan)
        $taken = Attendance::where('group_subject_id', $class->id)->where('date', '>=', today()->subDays(40)->toDateString())
            ->selectRaw('DATE(date) as d, COUNT(DISTINCT session) as s')->groupBy('d')->pluck('s', 'd');
        // La lista de días es la de la semana actual (se reinicia cada lunes); los días anteriores se abren desde el calendario o el historial.
        $recent = collect();
        $weekStart = today()->startOfWeek(\Carbon\Carbon::MONDAY);
        for ($d = $weekStart->copy(); $d->lte($weekStart->copy()->endOfWeek(\Carbon\Carbon::SUNDAY)); $d->addDay()) {
            if (in_array($d->dayOfWeekIso, $classDays, true)) {
                $iso = $d->toDateString();
                $recent->push(['date' => $iso, 'label' => ucfirst($d->isoFormat('dddd D')), 'today' => $d->isToday(), 'future' => $d->isFuture(),
                    'locked' => $d->lt(today()->subDays(\App\Http\Controllers\Docente\AttendanceController::EDIT_DAYS)),
                    'sessions' => $sessionsOn($d->dayOfWeekIso), 'taken' => (int) ($taken[$iso] ?? 0)]);
            }
        }

        $data['attendance'] = [
            'date' => $date->toDateString(),
            'date_label' => ucfirst($date->isoFormat('dddd D [de] MMMM YYYY')),
            'is_today' => $date->isToday(),
            'session' => $session,
            'sessions' => $sessionsOn($date->dayOfWeekIso),
            'class_days' => $classDays,
            'class_days_label' => collect($classDays)->map(fn ($d) => Schedule::DAYS[$d])->implode(', '),
            'has_schedule' => $days->isNotEmpty(),
            'taken' => $records->isNotEmpty(),
            // Plazo de captura y hora de inicio
            'edit_days' => \App\Http\Controllers\Docente\AttendanceController::EDIT_DAYS,
            'locked' => $date->lt(today()->subDays(\App\Http\Controllers\Docente\AttendanceController::EDIT_DAYS)),
            'editable_until' => ucfirst($date->copy()->addDays(\App\Http\Controllers\Docente\AttendanceController::EDIT_DAYS)->isoFormat('dddd D [de] MMMM')),
            'starts_at' => ($st = \App\Http\Controllers\Docente\AttendanceController::sessionStart($class, $date, $session)),
            'not_started' => $date->isToday() && $st && now()->format('H:i') < $st,
            'recorded_by' => $records->first()?->recorder?->full_name,
            'recorded_at' => $records->max('updated_at')?->format('d/m/Y H:i'),
            'rows' => $students->map(fn ($s) => [
                'student_id' => $s['id'],
                'status' => $records[$s['id']]->status ?? 'presente',
                'notes' => $records[$s['id']]->notes ?? null,
            ])->values(),
            'recent' => $recent->values(),
            'week_label' => 'Semana del ' . $weekStart->isoFormat('D [de] MMM') . ' al ' . $weekStart->copy()->addDays(6)->isoFormat('D [de] MMM'),
            'history' => Attendance::where('group_subject_id', $class->id)
                ->selectRaw('date, session, SUM(status = "presente") as presentes, SUM(status = "falta") as faltas, SUM(status = "retardo") as retardos, SUM(status = "justificada") as justificadas, MAX(updated_at) as updated')
                ->groupBy('date', 'session')->orderByDesc('date')->limit(30)->get()
                ->map(fn ($r) => ['date' => Carbon::parse($r->date)->toDateString(), 'label' => ucfirst(Carbon::parse($r->date)->isoFormat('ddd D MMM YYYY')),
                    'session' => $r->session, 'presentes' => (int) $r->presentes, 'faltas' => (int) $r->faltas,
                    'retardos' => (int) $r->retardos, 'justificadas' => (int) $r->justificadas,
                    'updated' => $r->updated ? Carbon::parse($r->updated)->format('d/m/Y H:i') : null]),
        ];
        $data['options'] = ['attendanceStatuses' => Options::from(Attendance::STATUSES)];

        return Inertia::render($this->view($request, 'Docente/Clase/Asistencia', 'App/Docente/Lista'), $data);
    }

    public function classroom(Request $request, GroupSubject $class)
    {
        $data = $this->base($request, $class, 'classroom');
        $data['assignments'] = Assignment::withCount(['submissions', 'submissions as graded_count' => fn ($q) => $q->whereNotNull('score')])
            ->with('criterion:id,name')->where('group_subject_id', $class->id)->latest('due_at')->get()
            ->map(fn ($a) => [
                ...$a->only(['id', 'type', 'title', 'description', 'max_score', 'is_published', 'period_id', 'topic_id', 'evaluation_criterion_id']),
                'due_at' => $a->due_at?->format('Y-m-d H:i'), 'due_label' => $a->due_at?->format('d/m/Y H:i'),
                'overdue' => $a->due_at?->isPast(), 'criterion' => $a->criterion?->name,
                'submissions_count' => $a->submissions_count, 'graded_count' => $a->graded_count,
                'files' => $a->fileLinks('archivos.tarea'),
                'rubric' => \App\Support\ClassroomFeed::rubric($a->rubric),
                'links' => \App\Support\ClassroomFeed::links($a->links),
                'created_ago' => $a->created_at?->diffForHumans(),
                'created_label' => $a->created_at?->isoFormat('D [de] MMM, HH:mm'),
                'due_full' => $a->due_at ? ucfirst($a->due_at->calendar(null, ['sameDay' => '[Hoy], HH:mm', 'nextDay' => '[Mañana], HH:mm', 'lastDay' => '[Ayer], HH:mm', 'nextWeek' => 'dddd, HH:mm', 'lastWeek' => 'D [de] MMM YYYY, HH:mm', 'sameElse' => 'D [de] MMM YYYY, HH:mm'])) : null,
                'students_total' => count($data['students']),
                'comments_count' => \App\Models\ClassComment::where('commentable_type', 'assignment')->where('commentable_id', $a->id)->where('is_private', false)->count(),
            ]);
        // Calificaciones (como Google Classroom): alumnos × tareas publicadas
        $published = Assignment::where('group_subject_id', $class->id)->where('is_published', true)->orderByDesc('due_at')->orderByDesc('id')->get();
        $subs = \App\Models\Submission::whereIn('assignment_id', $published->pluck('id'))->get()->groupBy('student_id');
        $data['gradebook'] = [
            'columns' => $published->map(fn ($a) => [
                'id' => $a->id, 'title' => $a->title, 'type' => $a->type, 'max_score' => $a->max_score, 'has_rubric' => ! empty($a->rubric),
                'due' => $a->due_at ? ucfirst($a->due_at->isoFormat('D MMM')) : 'Sin fecha', 'overdue' => (bool) $a->due_at?->isPast(),
            ])->values(),
            'cells' => collect($data['students'])->mapWithKeys(function ($st) use ($subs, $published) {
                $mine = ($subs[$st['id']] ?? collect())->keyBy('assignment_id');
                return [$st['id'] => $published->mapWithKeys(function ($a) use ($mine) {
                    $s = $mine->get($a->id);
                    return [$a->id => [
                        'score' => $s?->score, 'status' => $s?->status, 'submitted' => (bool) $s?->submitted_at,
                        'late' => $s && $a->due_at && $s->submitted_at?->gt($a->due_at),
                        'missing' => ! $s?->submitted_at && (bool) $a->due_at?->isPast(),
                    ]];
                })];
            }),
        ];

        // Temas del Classroom de esta clase
        $data['topics'] = \App\Models\ClassTopic::where('group_subject_id', $class->id)->orderBy('position')->orderBy('id')->get()
            ->map(fn ($t) => ['value' => $t->id, 'label' => $t->name])->values();

        // Novedades: avisos y material de la clase
        $data['posts'] = \App\Support\ClassroomFeed::posts($class, $request->user(), true);
        $data['options'] = [
            'assignmentTypes' => Options::from(Assignment::TYPES),
            'criteriaAll' => EvaluationCriterion::where('group_subject_id', $class->id)->where('source', 'tareas')->with('period:id,name')->get()
                ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name . ' · ' . $c->period?->name, 'period_id' => $c->period_id]),
        ];

        return Inertia::render($this->view($request, 'Docente/Clase/Classroom', 'App/Docente/Clase'), $data);
    }

    public function evaluacion(Request $request, GroupSubject $class)
    {
        $data = $this->base($request, $class, 'evaluacion');
        $period = Period::find($data['periodId']);
        if ($period) {
            $calc = new GradeCalculator($class, $period);
            $data['evaluation'] = [
                'criteria' => $calc->criteria()->map(fn ($c) => $c->only(['id', 'name', 'type', 'source', 'weight'])),
                'total_weight' => $calc->totalWeight(),
                'matrix' => $calc->matrix(collect($data['students'])->pluck('id')->all()),
                'is_closed' => $period->is_closed,
            ];
        }
        $data['options'] = [
            'criterionTypes' => Options::from(EvaluationCriterion::TYPES),
            'criterionSources' => Options::from(EvaluationCriterion::SOURCES),
        ];

        return Inertia::render('Docente/Clase/Evaluacion', $data);
    }

    public function incidencias(Request $request, GroupSubject $class)
    {
        $data = $this->base($request, $class, 'incidencias');
        $data['incidents'] = Incident::with('student:id,name,paternal_surname,maternal_surname')->where('group_subject_id', $class->id)
            ->latest('date')->get()->map(fn ($i) => [
                'id' => $i->id, 'date' => $i->date?->format('d/m/Y'), 'student' => $i->student?->full_name, 'student_id' => $i->student_id,
                'category' => Incident::CATEGORIES[$i->category] ?? $i->category, 'positive' => $i->isPositive(),
                'description' => $i->description, 'follow_up' => $i->follow_up, 'priority' => $i->priority, 'status' => $i->status,
                'evidence_url' => $i->evidenceUrl(), 'evidence_name' => $i->evidenceName(),
            ]);
        $data['options'] = [
            'categories' => Options::from(Incident::CATEGORIES),
            'priorities' => Options::from(Incident::PRIORITIES),
            'statuses' => Options::from(Incident::STATUSES),
        ];

        return Inertia::render('Docente/Clase/Incidencias', $data);
    }

    public function alumnos(Request $request, GroupSubject $class)
    {
        $data = $this->base($request, $class, 'alumnos');
        $ids = collect($data['students'])->pluck('id');
        $att = Attendance::where('group_subject_id', $class->id)->whereIn('student_id', $ids)
            ->selectRaw('student_id, COUNT(*) as total, SUM(status = "falta") as faltas, SUM(status = "retardo") as retardos')
            ->groupBy('student_id')->get()->keyBy('student_id');
        $avg = \App\Models\GradeRecord::where('group_subject_id', $class->id)->whereIn('student_id', $ids)->whereNull('evaluation_criterion_id')
            ->selectRaw('student_id, AVG(score) as a')->groupBy('student_id')->pluck('a', 'student_id');
        $inc = Incident::where('group_subject_id', $class->id)->whereIn('student_id', $ids)->selectRaw('student_id, COUNT(*) as n')->groupBy('student_id')->pluck('n', 'student_id');

        $data['students'] = collect($data['students'])->map(function ($s) use ($att, $avg, $inc) {
            $a = $att[$s['id']] ?? null;
            return $s + [
                'attendance_rate' => $a && $a->total ? round(($a->total - $a->faltas) / $a->total * 100) : null,
                'absences' => (int) ($a->faltas ?? 0), 'lates' => (int) ($a->retardos ?? 0),
                'average' => isset($avg[$s['id']]) ? round($avg[$s['id']], 2) : null,
                'incidents' => (int) ($inc[$s['id']] ?? 0),
            ];
        })->values();

        return Inertia::render('Docente/Clase/Alumnos', $data);
    }

    /** Datos comunes de todas las secciones de una clase y registro de la "clase de trabajo". */
    public function base(Request $request, GroupSubject $class, string $section): array
    {
        abort_unless(Access::canManageClass($request->user(), $class), 403);
        $class->load(['group.grade', 'group.schoolYear', 'subject', 'teacher', 'schedules']);
        session(['docente.class_id' => $class->id, 'docente.section' => $section]);

        $students = $class->group->students()->get();
        $periods = Period::where('school_year_id', $class->group->school_year_id)->orderBy('order')->get();
        $periodId = (int) $request->query('period_id') ?: ($periods->first(fn ($p) => $p->starts_on && $p->ends_on && today()->between($p->starts_on, $p->ends_on))?->id ?? $periods->first()?->id);

        return [
            'course' => [
                'id' => $class->id, 'subject' => $class->subject?->name, 'group' => $class->group?->label, 'group_id' => $class->group_id,
                'school_year' => $class->group?->schoolYear?->name, 'teacher' => $class->teacher?->display_name,
                'schedule' => $class->schedules->sortBy(['day_of_week', 'starts_at'])->map(fn ($s) => mb_substr(Schedule::DAYS[$s->day_of_week], 0, 3) . ' ' . substr($s->starts_at, 0, 5))->values(),
            ],
            'section' => $section,
            'students' => $students->map(fn ($s) => [
                'id' => $s->id, 'list_name' => $s->listName(), 'enrollment_number' => $s->enrollment_number, 'photo_url' => $s->photoUrl(),
            ])->values(),
            'periods' => $periods->map(fn ($p) => ['value' => $p->id, 'label' => $p->name, 'is_closed' => $p->is_closed]),
            'periodId' => $periodId,
        ];
    }
}
