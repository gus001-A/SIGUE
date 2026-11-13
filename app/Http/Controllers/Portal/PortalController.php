<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Attendance;
use App\Models\Charge;
use App\Models\Document;
use App\Models\GradeRecord;
use App\Models\GroupSubject;
use App\Models\Incident;
use App\Models\Payment;
use App\Models\Period;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Submission;
use App\Services\GradeCalculator;
use App\Services\Notifier;
use App\Support\Options;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use App\Support\ClassroomFeed;

/**
 * Portal del alumno y de padres o tutores (MOD-ALU / MOD-TUT). El tutor
 * elige a cuál de sus hijos consultar (?alumno=ID); solo ve alumnos
 * vinculados a su cuenta.
 */
class PortalController extends Controller
{
    /** @return array{0: Student, 1: \Illuminate\Support\Collection} */
    private function context(Request $request): array
    {
        $user = $request->user();
        $children = collect();
        if ($user->guardian) {
            $children = $user->guardian->students()->where('students.status', '!=', 'baja')->orderBy('name')->get();
        }
        if ($user->student) {
            $children->prepend($user->student);
        }
        abort_if($children->isEmpty(), 403, 'Tu cuenta no está vinculada a ningún alumno.');

        $selected = $request->integer('alumno') ?: session('portal_student');
        $student = $children->firstWhere('id', $selected) ?? $children->first();
        session(['portal_student' => $student->id]);

        return [$student, $children];
    }

    private function render(Request $request, string $page, Student $student, $children, array $props)
    {
        $enrollment = $student->currentEnrollment()->with('group.grade', 'group.tutor', 'schoolYear')->first();

        // La app del alumno (/app) reutiliza los mismos datos con vistas tipo app móvil
        $prefix = $request->routeIs('app.*') ? 'App/' : 'Portal/';

        return Inertia::render($prefix . $page, $props + [
            'student' => [
                'id' => $student->id, 'full_name' => $student->full_name, 'first_name' => $student->name,
                'enrollment_number' => $student->enrollment_number, 'group' => $enrollment?->group?->label,
                'school_year' => $enrollment?->schoolYear?->name, 'photo_url' => $student->photoUrl(),
                'level' => $enrollment?->group?->grade?->level, 'shift' => $enrollment?->group?->shift,
                'group_tutor' => $enrollment?->group?->tutor?->display_name,
            ],
            'children' => $children->map(fn ($c) => [
                'id' => $c->id, 'name' => $c->full_name, 'first_name' => $c->name, 'photo_url' => $c->photoUrl(),
                'group' => $c->currentEnrollment?->group?->label,
            ])->values(),
            'isGuardian' => (bool) $request->user()->guardian,
        ]);
    }

    private function classes(Student $student)
    {
        $e = $student->currentEnrollment;
        return $e ? GroupSubject::with(['subject:id,name', 'teacher:id,name,paternal_surname,maternal_surname,user_id', 'schedules'])
            ->where('group_id', $e->group_id)->get()->sortBy('subject.name')->values() : collect();
    }

    public function inicio(Request $request)
    {
        [$student, $children] = $this->context($request);
        $classes = $this->classes($student);
        $classIds = $classes->pluck('id');
        $today = Carbon::today();

        $att = Attendance::where('student_id', $student->id)->where('date', '>=', $today->copy()->subDays(30))->pluck('status');
        $pending = Assignment::whereIn('group_subject_id', $classIds)->where('is_published', true)
            ->where(fn ($q) => $q->whereNull('due_at')->orWhere('due_at', '>=', now()))
            ->whereDoesntHave('submissions', fn ($q) => $q->where('student_id', $student->id)->whereNotNull('submitted_at'))
            ->with('groupSubject.subject:id,name')->orderByRaw('due_at IS NULL')->orderBy('due_at')->get();
        $pendingByClass = $pending->countBy('group_subject_id');

        $published = GradeRecord::where('student_id', $student->id)->whereNull('evaluation_criterion_id')->where('is_published', true)->avg('score');
        $debt = Charge::where('student_id', $student->id)->whereIn('status', ['pendiente', 'parcial'])->get();

        return $this->render($request, 'Inicio', $student, $children, [
            'summary' => [
                'average' => $published ? round($published, 1) : null,
                'attendance' => $att->count() ? round($att->filter(fn ($s) => $s !== 'falta')->count() / $att->count() * 100) : null,
                'absences' => $att->filter(fn ($s) => $s === 'falta')->count(),
                'pending' => $pending->count(),
                'debt' => round($debt->sum(fn ($c) => $c->balance()), 2),
                'overdue' => $debt->filter->isOverdue()->count(),
                'incidents' => Incident::where('student_id', $student->id)->where('date', '>=', $today->copy()->subDays(30))->count(),
            ],
            'pending' => $pending->take(6)->map(fn ($a) => ['id' => $a->id, 'title' => $a->title, 'subject' => $a->groupSubject?->subject?->name, 'type' => $a->type,
                'due' => $a->due_at?->format('d/m/Y H:i'), 'due_label' => $a->due_at ? ucfirst($a->due_at->isoFormat('ddd D MMM, HH:mm')) : null,
                'soon' => $a->due_at && $a->due_at->lt(now()->addDays(2))])->values(),
            'classes' => $classes->map(fn ($c) => ['id' => $c->id, 'subject' => $c->subject?->name, 'teacher' => $c->teacher?->display_name,
                'pending' => (int) ($pendingByClass[$c->id] ?? 0),
                'upcoming' => $pending->where('group_subject_id', $c->id)->take(2)
                    ->map(fn ($a) => ['id' => $a->id, 'title' => $a->title, 'due' => static::dueLabel($a->due_at), 'overdue' => (bool) $a->due_at?->isPast()])->values(),
            ])->values(),
            'today' => Schedule::whereIn('group_subject_id', $classIds)->where('day_of_week', $today->dayOfWeekIso)->orderBy('starts_at')
                ->with('groupSubject.subject:id,name', 'groupSubject.teacher')->get()
                ->map(fn ($s) => ['subject' => $s->groupSubject?->subject?->name, 'teacher' => $s->groupSubject?->teacher?->display_name,
                    'starts_at' => substr($s->starts_at, 0, 5), 'ends_at' => substr($s->ends_at, 0, 5), 'room' => $s->room,
                    'now' => now()->format('H:i') >= substr($s->starts_at, 0, 5) && now()->format('H:i') < substr($s->ends_at, 0, 5) && $today->isToday()]),
            'grades' => $this->currentGrades($student, $classes),
            'attendanceDays' => $this->attendanceDays($student),
            'incidents' => Incident::where('student_id', $student->id)->latest('date')->limit(3)->get()
                ->map(fn ($i) => ['id' => $i->id, 'date' => $i->date?->format('d/m/Y'), 'category' => Incident::CATEGORIES[$i->category] ?? $i->category,
                    'positive' => $i->isPositive(), 'description' => $i->description]),
            'missingDocs' => \App\Models\DocumentType::where('is_required', true)
                ->whereNotIn('id', \App\Models\Document::where('student_id', $student->id)->whereIn('status', ['pendiente', 'validado'])->pluck('document_type_id')->filter())
                ->count(),
            'passing' => (float) (\App\Support\Tenant::institution()?->settings['passing_score'] ?? 6),
        ]);
    }

    /** Calificación vigente por materia: final publicada del periodo actual o, si no hay, la parcial calculada. */
    private function currentGrades(Student $student, $classes): array
    {
        $e = $student->currentEnrollment;
        if (! $e) return [];
        $periods = Period::where('school_year_id', $e->school_year_id)->orderBy('order')->get();
        $period = $periods->first(fn ($p) => $p->starts_on && $p->ends_on && today()->between($p->starts_on, $p->ends_on)) ?? $periods->first();
        if (! $period) return [];

        return $classes->map(function ($c) use ($period, $student) {
            $final = GradeRecord::where(['group_subject_id' => $c->id, 'period_id' => $period->id, 'student_id' => $student->id])
                ->whereNull('evaluation_criterion_id')->where('is_published', true)->value('score');
            $partial = $final === null ? ((new GradeCalculator($c, $period))->matrix([$student->id])[$student->id]['computed'] ?? null) : null;
            return ['subject' => $c->subject?->name, 'score' => $final ?? $partial, 'final' => $final !== null, 'period' => $period->name];
        })->values()->all();
    }

    /** Asistencia de los últimos 10 días hábiles (conteo por estado). */
    private function attendanceDays(Student $student): array
    {
        $days = collect();
        for ($d = today(); $days->count() < 10; $d->subDay()) {
            if ($d->isWeekday()) $days->prepend($d->copy());
        }
        $rows = Attendance::where('student_id', $student->id)->whereBetween('date', [$days->first()->toDateString(), today()->toDateString()])->get()
            ->groupBy(fn ($a) => $a->date->toDateString());
        return [
            'labels' => $days->map(fn ($d) => ucfirst($d->isoFormat('ddd D')))->values(),
            'series' => collect(['presente' => 'Asistencias', 'retardo' => 'Retardos', 'justificada' => 'Justificadas', 'falta' => 'Faltas'])
                ->map(fn ($l, $k) => ['key' => $k, 'name' => $l, 'data' => $days->map(fn ($d) => ($rows[$d->toDateString()] ?? collect())->where('status', $k)->count())->values()])->values(),
        ];
    }

    public function calificaciones(Request $request)
    {
        [$student, $children] = $this->context($request);
        $classes = $this->classes($student);
        $enrollment = $student->currentEnrollment;
        $periods = $enrollment ? Period::where('school_year_id', $enrollment->school_year_id)->orderBy('order')->get() : collect();

        $rows = $classes->map(function ($c) use ($periods, $student) {
            $byPeriod = [];
            foreach ($periods as $p) {
                $final = GradeRecord::where(['group_subject_id' => $c->id, 'period_id' => $p->id, 'student_id' => $student->id])
                    ->whereNull('evaluation_criterion_id')->where('is_published', true)->value('score');
                $calc = new GradeCalculator($c, $p);
                $m = $calc->matrix([$student->id])[$student->id] ?? null;
                $byPeriod[$p->id] = [
                    'final' => $final,
                    'partial' => $m['computed'] ?? null,
                    'criteria' => $calc->criteria()->map(fn ($cr) => ['name' => $cr->name, 'weight' => $cr->weight, 'score' => $m['criteria'][$cr->id] ?? null])->values(),
                ];
            }
            $finals = collect($byPeriod)->pluck('final')->filter(fn ($v) => $v !== null);
            return [
                'id' => $c->id, 'subject' => $c->subject?->name, 'teacher' => $c->teacher?->display_name,
                'periods' => $byPeriod, 'average' => $finals->isNotEmpty() ? round($finals->avg(), 2) : null,
            ];
        });

        $passing = (float) (\App\Support\Tenant::institution()?->settings['passing_score'] ?? 6);

        return $this->render($request, 'Calificaciones', $student, $children, [
            'periods' => $periods->map(fn ($p) => ['id' => $p->id, 'name' => $p->name]),
            'rows' => $rows,
            'passing' => $passing,
        ]);
    }

    public function tareas(Request $request)
    {
        [$student, $children] = $this->context($request);
        $classIds = $this->classes($student)->pluck('id');

        $items = Assignment::with(['groupSubject.subject:id,name', 'groupSubject.teacher', 'period:id,name', 'topic:id,name,position'])->whereIn('group_subject_id', $classIds)->where('is_published', true)
            ->orderByRaw('due_at IS NULL')->orderByDesc('due_at')->get();
        $subs = Submission::where('student_id', $student->id)->whereIn('assignment_id', $items->pluck('id'))->get()->keyBy('assignment_id');

        return $this->render($request, 'Tareas', $student, $children, [
            'items' => $this->assignmentRows($request, $items, $subs, $student),
            'canSubmit' => (bool) $request->user()->student && $request->user()->student->id === $student->id,
        ]);
    }

    /** Tareas con entrega, rúbrica y comentarios (de la clase y privados del alumno). */
    private function assignmentRows(Request $request, $items, $subs, Student $student)
    {
        $user = $request->user();
        $public = ClassroomFeed::commentsFor('assignment', $items->pluck('id')->all(), $user, false);
        $private = ClassroomFeed::commentsFor('assignment', $items->pluck('id')->all(), $user, false, true, [$student->id]);

        return $items->map(function ($a) use ($subs, $public, $private, $student) {
            $s = $subs->get($a->id);
            return [
                'id' => $a->id, 'type' => $a->type, 'title' => $a->title, 'description' => $a->description, 'class_id' => $a->group_subject_id,
                'period_id' => $a->period_id, 'period' => $a->period?->name,
                'topic_id' => $a->topic_id, 'topic' => $a->topic?->name, 'topic_pos' => $a->topic?->position,
                'subject' => $a->groupSubject?->subject?->name, 'due_at' => $a->due_at?->format('d/m/Y H:i'),
                'overdue' => $a->due_at?->isPast() ?? false, 'max_score' => $a->max_score,
                'files' => $a->fileLinks('archivos.tarea'),
                'rubric' => ClassroomFeed::rubric($a->rubric),
                'links' => ClassroomFeed::links($a->links),
                'teacher' => $a->groupSubject?->teacher?->display_name,
                'created_label' => $a->created_at?->isoFormat('D [de] MMM'),
                'comments' => $public->get($a->id, collect())->values(),
                'private' => $private->get($a->id . ':' . $student->id, collect())->values(),
                'submission' => $s ? [
                    'status' => $s->status, 'submitted_at' => $s->submitted_at?->format('d/m/Y H:i'), 'content' => $s->content,
                    'files' => $s->fileLinks('archivos.entrega'),
                    'score' => $s->score, 'feedback' => $s->feedback,
                    // La rúbrica calificada solo se muestra cuando ya hay calificación
                    'rubric_scores' => $s->score !== null ? ($s->rubric_scores ?? []) : [],
                ] : null,
            ];
        })->values();
    }

    /**
     * Classroom del alumno (como Google Classroom):
     *  - Sin clase elegida: panel con "Fecha de entrega próxima" y las tarjetas de sus clases.
     *  - Con ?clase=: la clase con Tablón (avisos y material), Trabajo de clase (por temas) y Personas.
     */
    public function clases(Request $request)
    {
        [$student, $children] = $this->context($request);
        $classes = $this->classes($student);
        $current = $request->filled('clase') ? $classes->firstWhere('id', $request->integer('clase')) : null;
        $user = $request->user();
        $with = ['groupSubject.subject:id,name', 'groupSubject.teacher', 'period:id,name', 'topic:id,name,position'];

        $all = Assignment::with($with)->whereIn('group_subject_id', $classes->pluck('id'))->where('is_published', true)
            ->orderByRaw('due_at IS NULL')->orderBy('due_at')->get();
        $subs = Submission::where('student_id', $student->id)->whereIn('assignment_id', $all->pluck('id'))->get()->keyBy('assignment_id');
        $pendingOf = fn ($a) => ! $subs->get($a->id)?->submitted_at && $subs->get($a->id)?->score === null;
        $pending = $all->filter($pendingOf)->countBy('group_subject_id');
        $enrollment = $student->currentEnrollment()->with('group.grade')->first();

        $props = [
            'classes' => $classes->map(fn ($c) => [
                'id' => $c->id, 'subject' => $c->subject?->name, 'teacher' => $c->teacher?->display_name, 'pending' => (int) ($pending[$c->id] ?? 0),
                'group' => $enrollment?->group?->label, 'level' => $enrollment?->group?->grade?->level,
                'teacher_initials' => ClassroomFeed::initials($c->teacher?->display_name),
                // Tareas por entregar de esta clase (como en la tarjeta de Google Classroom): primero las más próximas
                'upcoming' => $all->where('group_subject_id', $c->id)->filter($pendingOf)
                    ->sortBy(fn ($a) => $a->due_at ? ($a->due_at->isPast() ? 1 : 0) . $a->due_at->timestamp : '2')->take(3)
                    ->map(fn ($a) => ['id' => $a->id, 'title' => $a->title, 'type' => $a->type, 'overdue' => (bool) $a->due_at?->isPast(),
                        'due' => static::dueLabel($a->due_at)])->values(),
            ])->values(),
            // Fecha de entrega próxima: lo pendiente que vence en los próximos 7 días
            'dueSoon' => $all->filter(fn ($a) => $pendingOf($a) && $a->due_at && $a->due_at->between(now(), now()->addDays(7)))
                ->map(fn ($a) => ['id' => $a->id, 'title' => $a->title, 'subject' => $a->groupSubject?->subject?->name, 'due' => ucfirst($a->due_at->isoFormat('dddd D [de] MMM, HH:mm'))])->values(),
            'current' => null, 'posts' => [], 'items' => [], 'people' => null,
            'canSubmit' => (bool) $user->student && $user->student->id === $student->id,
        ];
        if ($current) {
            $items = $all->where('group_subject_id', $current->id)->values();
            $props['current'] = ['id' => $current->id, 'subject' => $current->subject?->name, 'teacher' => $current->teacher?->display_name,
                'group' => $enrollment?->group?->label, 'level' => $enrollment?->group?->grade?->level];
            $props['posts'] = ClassroomFeed::posts($current, $user, false);
            $props['items'] = $this->assignmentRows($request, $items, $subs, $student);
            $props['people'] = [
                'teacher' => $current->teacher?->display_name,
                'classmates' => $current->group?->students()->get()->map(fn ($s) => ['id' => $s->id, 'name' => $s->full_name, 'photo_url' => $s->photoUrl()])->sortBy('name')->values(),
            ];
        }

        return $this->render($request, 'Clases', $student, $children, $props);
    }

    /** Avisos (notificaciones) del usuario para la app. */
    public function avisos(Request $request)
    {
        [$student, $children] = $this->context($request);

        return $this->render($request, 'Avisos', $student, $children, [
            'items' => \App\Models\Notification::where('user_id', $request->user()->id)->latest()->limit(60)->get()->map->toView()->values(),
        ]);
    }

    /** "Hoy, 23:59" · "Mañana, 23:59" · "Vie 15 nov" · "Venció 30 oct" · "Sin fecha". */
    public static function dueLabel($due): string
    {
        if (! $due) return 'Sin fecha de entrega';
        if ($due->isPast()) return 'Venció el ' . $due->isoFormat('D [de] MMM');
        if ($due->isToday()) return 'Hoy, ' . $due->format('H:i');
        if ($due->isTomorrow()) return 'Mañana, ' . $due->format('H:i');
        return ucfirst($due->isoFormat('dddd D [de] MMM'));
    }

    /** Página de una tarea (como Google Classroom): instrucciones, tu trabajo y comentarios. */
    public function tarea(Request $request, Assignment $assignment)
    {
        [$student, $children] = $this->context($request);
        abort_unless($assignment->is_published && $this->classes($student)->pluck('id')->contains($assignment->group_subject_id), 404);
        $assignment->load(['groupSubject.subject:id,name', 'groupSubject.teacher', 'period:id,name', 'topic:id,name,position']);
        $subs = Submission::where('student_id', $student->id)->where('assignment_id', $assignment->id)->get()->keyBy('assignment_id');

        return $this->render($request, 'Tarea', $student, $children, [
            'item' => $this->assignmentRows($request, collect([$assignment]), $subs, $student)->first(),
            'classId' => $assignment->group_subject_id,
            'canSubmit' => (bool) $request->user()->student && $request->user()->student->id === $student->id,
        ]);
    }

    /** Anular entrega: el alumno retira su entrega (si aún no está calificada) para modificarla. */
    public function anular(Request $request, Assignment $assignment)
    {
        $student = $request->user()->student;
        abort_unless($student, 403, 'Solo el alumno puede anular su entrega.');
        $sub = Submission::where('assignment_id', $assignment->id)->where('student_id', $student->id)->firstOrFail();
        if ($sub->score !== null) {
            return back()->with('error', 'La entrega ya fue calificada; no puede anularse.');
        }
        $sub->update(['submitted_at' => null, 'status' => 'anulada']);

        return back()->with('success', 'Entrega anulada. Puedes modificarla y volver a entregar.');
    }

    /** Entrega de tarea (solo el propio alumno). */
    public function entregar(Request $request, Assignment $assignment)
    {
        $student = $request->user()->student;
        abort_unless($student, 403, 'Solo el alumno puede entregar sus tareas.');
        abort_unless($assignment->is_published && $assignment->groupSubject->group->students()->where('students.id', $student->id)->exists(), 403);

        $max = Submission::MAX_FILES;
        $data = $request->validate([
            'content' => 'nullable|string|max:10000',
            'files' => "nullable|array|max:{$max}",
            'files.*' => 'file|max:20480',
            'remove_files' => 'nullable|array',
            'remove_files.*' => 'integer',
        ], ['files.max' => "Puedes adjuntar máximo {$max} archivos.", 'files.*.max' => 'Cada archivo puede pesar máximo 20 MB.']);

        // Si hubo una entrega eliminada (lógicamente), se reutiliza limpia para no chocar con el índice único
        $sub = Submission::withTrashed()->firstOrNew(['assignment_id' => $assignment->id, 'student_id' => $student->id]);
        if ($sub->trashed()) {
            $sub->restore();
            $sub->fill(['files' => [], 'content' => null, 'submitted_at' => null, 'score' => null, 'rubric_scores' => null, 'status' => 'asignada']);
        }
        // Como en Google Classroom: ya calificada puede "volver a enviar" (conserva la calificación hasta que el docente la cambie)
        // y sin archivos ni texto se toma como "Marcar como completado".
        if ($sub->exists && $sub->submitted_at && $sub->score === null) {
            return back()->with('error', 'Ya entregaste esta tarea. Anula la entrega para modificarla.');
        }
        $remove = (array) ($data['remove_files'] ?? []);
        $uploads = (array) $request->file('files', []);
        $total = $sub->countAfter($remove, count($uploads));
        if ($total > $max) {
            return back()->withErrors(['files' => "Puedes adjuntar máximo {$max} archivos por entrega."]);
        }
        $content = trim((string) ($data['content'] ?? ''));
        $sub->syncFiles($remove, $uploads, "instituciones/{$student->institution_id}/entregas/{$assignment->id}");
        $sub->content = $content !== '' ? $content : null;
        $sub->submitted_at = now();
        $sub->status = $assignment->due_at && now()->gt($assignment->due_at) ? 'tarde' : 'entregada';
        $sub->save();

        if ($teacherUser = $assignment->groupSubject->teacher?->user_id) {
            Notifier::send($teacherUser, 'entrega', 'Nueva entrega: ' . $assignment->title, $student->full_name,
                route('docente.clase', ['class' => $assignment->group_subject_id, 'tab' => 'classroom']), $sub);
        }

        return back()->with('success', $sub->status === 'tarde' ? 'Entrega registrada fuera de tiempo.' : 'Entrega registrada.');
    }

    public function horario(Request $request)
    {
        [$student, $children] = $this->context($request);
        $classes = $this->classes($student);

        return $this->render($request, 'Horario', $student, $children, [
            'schedule' => $classes->flatMap(fn ($c) => $c->schedules->map(fn ($s) => [
                'day' => $s->day_of_week, 'starts_at' => substr($s->starts_at, 0, 5), 'ends_at' => substr($s->ends_at, 0, 5),
                'subject' => $c->subject?->name, 'teacher' => $c->teacher?->display_name, 'room' => $s->room,
            ]))->sortBy(['day', 'starts_at'])->values(),
            'days' => Schedule::DAYS,
            // Receso del turno del grupo (configurado por plantel)
            'break' => ($g = $student->currentEnrollment?->group) ? $g->campus()->first()?->breakFor($g->shift) : null,
            'teachers' => $classes->map(fn ($c) => ['subject' => $c->subject?->name, 'teacher' => $c->teacher?->display_name, 'user_id' => $c->teacher?->user_id]),
        ]);
    }

    public function asistencias(Request $request)
    {
        [$student, $children] = $this->context($request);
        $rows = Attendance::with('groupSubject.subject:id,name')->where('student_id', $student->id)->latest('date')->limit(300)->get();

        return $this->render($request, 'Asistencias', $student, $children, [
            'summary' => collect(Attendance::STATUSES)->map(fn ($l, $k) => $rows->where('status', $k)->count()),
            'bySubject' => $rows->groupBy('group_subject_id')->map(fn ($r) => [
                'subject' => $r->first()->groupSubject?->subject?->name,
                'total' => $r->count(),
                'falta' => $r->where('status', 'falta')->count(),
                'retardo' => $r->where('status', 'retardo')->count(),
                'rate' => round($r->filter(fn ($x) => $x->status !== 'falta')->count() / max(1, $r->count()) * 100),
            ])->values(),
            'incidents' => $rows->whereIn('status', ['falta', 'retardo', 'justificada'])->take(60)->map(fn ($a) => [
                'date' => $a->date?->format('d/m/Y'), 'subject' => $a->groupSubject?->subject?->name, 'status' => $a->status, 'notes' => $a->notes,
            ])->values(),
        ]);
    }

    public function incidencias(Request $request)
    {
        [$student, $children] = $this->context($request);

        return $this->render($request, 'Incidencias', $student, $children, [
            'items' => Incident::with(['teacher:id,name,paternal_surname,maternal_surname', 'groupSubject.subject:id,name'])
                ->where('student_id', $student->id)->latest('date')->get()
                ->map(fn ($i) => [
                    'id' => $i->id, 'date' => $i->date?->format('d/m/Y'), 'category' => Incident::CATEGORIES[$i->category] ?? $i->category,
                    'positive' => $i->isPositive(), 'description' => $i->description, 'priority' => $i->priority,
                    'status' => Incident::STATUSES[$i->status] ?? $i->status, 'teacher' => $i->teacher?->display_name,
                    'subject' => $i->groupSubject?->subject?->name, 'follow_up' => $i->follow_up, 'evidence_url' => $i->evidenceUrl(), 'evidence_name' => $i->evidenceName(),
                ]),
        ]);
    }

    public function documentos(Request $request)
    {
        [$student, $children] = $this->context($request);

        return $this->render($request, 'Documentos', $student, $children, [
            'documents' => Document::with('type')->where('student_id', $student->id)->latest()->get()->map->toView(),
            'documentTypes' => Options::documentTypes(),
            'missing' => \App\Models\DocumentType::where('is_required', true)
                ->whereNotIn('id', Document::where('student_id', $student->id)->whereIn('status', ['pendiente', 'validado'])->pluck('document_type_id')->filter())
                ->pluck('name'),
        ]);
    }

    public function pagos(Request $request)
    {
        [$student, $children] = $this->context($request);
        $charges = Charge::where('student_id', $student->id)->where('status', '!=', 'cancelado')->orderByDesc('due_on')->get();

        return $this->render($request, 'Pagos', $student, $children, [
            'charges' => $charges->map(fn ($c) => [
                'id' => $c->id, 'concept' => $c->concept, 'type' => $c->type, 'total' => $c->total(), 'discount' => $c->discount,
                'paid' => $c->paid(), 'balance' => $c->balance(), 'due_on' => $c->due_on?->format('d/m/Y'), 'status' => $c->status,
                'overdue' => $c->isOverdue(),
            ]),
            'payments' => Payment::with('charge:id,concept')->where('student_id', $student->id)->where('status', 'aplicado')->latest('paid_at')->get()
                ->map(fn ($p) => ['id' => $p->id, 'folio' => $p->folio, 'concept' => $p->charge?->concept ?? 'Pago a cuenta', 'amount' => $p->amount,
                    'method' => Payment::METHODS[$p->method] ?? $p->method, 'paid_at' => $p->paid_at?->format('d/m/Y'),
                    'receipt_url' => route('portal.recibo', $p)]),
            'balance' => round($charges->whereIn('status', ['pendiente', 'parcial'])->sum(fn ($c) => $c->balance()), 2),
            'scholarships' => $student->scholarships()->get()->filter->isActive()->map(fn ($s) => ['name' => $s->name, 'percentage' => $s->percentage, 'amount' => $s->amount])->values(),
        ]);
    }

    public function recibo(Request $request, Payment $payment)
    {
        [$student, $children] = $this->context($request);
        abort_unless($children->pluck('id')->contains($payment->student_id), 403);

        return app(\App\Http\Controllers\Admin\FinanceController::class)->receipt($payment);
    }
}
