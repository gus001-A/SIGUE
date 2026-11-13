<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Rules\Visible;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\GroupSubject;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\SchoolYear;
use App\Models\Subject;
use App\Support\Options;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Grupos, asignación de asignaturas/docentes y horarios. */
class GroupController extends Controller
{
    public function index(Request $request)
    {
        $yearId = $request->integer('school_year_id') ?: SchoolYear::current()?->id;

        $groups = Group::with(['grade:id,name,order,level', 'campus:id,name', 'tutor:id,name,paternal_surname,maternal_surname'])
            ->withCount(['enrollments as students_count' => fn ($q) => $q->where('status', 'autorizada'), 'classes'])
            ->where('school_year_id', $yearId)
            ->get()->sortBy(fn ($g) => sprintf('%03d-%s', $g->grade?->order, $g->name))->values()
            ->map(fn ($g) => $this->formatGroup($g));

        return Inertia::render('Admin/Grupos/Index', [
            'groups' => $groups,
            'schoolYearId' => $yearId,
            'options' => [
                'schoolYears' => Options::schoolYears(),
                'grades' => Options::grades(),
                'campuses' => Options::campuses(),
                'teachers' => Options::teachers(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $group = Group::create($this->validated($request));

        if ($request->boolean('with_subjects')) {
            Subject::where('grade_id', $group->grade_id)->where('is_active', true)
                ->get()->each(fn ($s) => GroupSubject::firstOrCreate(['group_id' => $group->id, 'subject_id' => $s->id]));
        }

        // Redirigimos al index en lugar del show para que la tabla se actualice
        return redirect()->route('grupos.index', ['school_year_id' => $group->school_year_id])
            ->with('success', 'Grupo creado correctamente.');
    }

    public function update(Request $request, Group $group)
    {
        $group->update($this->validated($request));

        return back()->with('success', 'Grupo actualizado correctamente.');
    }

    public function destroy(Group $group)
    {
        if ($group->enrollments()->exists()) {
            return back()->with('error', 'El grupo tiene inscripciones registradas; no se puede eliminar.');
        }
        $group->delete();

        return redirect()->route('grupos.index')->with('success', 'Grupo eliminado.');
    }

    /** Vista del grupo: gestión de los alumnos inscritos. */
    public function show(Request $request, Group $group)
    {
        $group->load(['grade', 'campus:id,name', 'schoolYear:id,name', 'tutor'])->loadCount('classes');

        $students = $group->students()->with('guardians:id,name,paternal_surname,maternal_surname,phone')->get();
        $user = $request->user();
        $canEnroll = $user->hasPermission('matricula.gestionar');

        // Inscripciones pendientes de autorizar en este grupo
        $pending = Enrollment::with('student:id,name,paternal_surname,maternal_surname,enrollment_number,status')
            ->where('group_id', $group->id)->where('status', 'pendiente')->get();

        return Inertia::render('Admin/Grupos/Show', [
            'group' => $this->groupHeader($group),
            'students' => $students->map(fn ($s) => [
                'id' => $s->id, 'enrollment_number' => $s->enrollment_number, 'list_name' => $s->listName(), 'status' => $s->status,
                'curp' => $s->curp, 'email' => $s->email, 'phone' => $s->phone, 'gender' => $s->gender, 'photo_url' => $s->photoUrl(),
                'guardian' => $s->guardians->first()?->full_name, 'guardian_phone' => $s->guardians->first()?->phone,
            ]),
            'pending' => $pending->map(fn ($e) => [
                'id' => $e->id, 'student_id' => $e->student_id, 'enrollment_number' => $e->student?->enrollment_number,
                'list_name' => $e->student?->listName(), 'type' => $e->type, 'enrolled_on' => $e->enrolled_on?->format('d/m/Y'),
            ]),
            'can' => [
                'enroll' => $canEnroll,
                'authorize' => $user->hasPermission('matricula.autorizar'),
            ],
            'options' => [
                'candidates' => $canEnroll ? $this->candidates($group) : [],
            ],
        ]);
    }

    /** Vista del horario: asignaturas, docentes y armado del horario semanal. */
    public function schedule(Group $group)
    {
        $group->load(['grade', 'campus:id,name', 'schoolYear:id,name', 'tutor',
            'classes.subject:id,name,code,hours_per_week', 'classes.teacher:id,name,paternal_surname,maternal_surname,gender,degree,user_id,email', 'classes.schedules']);

        return Inertia::render('Admin/Grupos/Horario', [
            'group' => [...$this->groupHeader($group), 'students_count' => $group->students()->count()],
            'classes' => $group->classes->sortBy('subject.name')->values()->map(fn ($c) => [
                'id' => $c->id,
                'subject_id' => $c->subject_id,
                'subject' => $c->subject?->name,
                'code' => $c->subject?->code,
                'hours_per_week' => $c->subject?->hours_per_week,
                'teacher_id' => $c->teacher_id,
                'teacher' => $c->teacher?->display_name,
                'teacher_user_id' => $c->teacher?->user_id,
                'schedules' => $c->schedules->map(fn ($s) => [
                    'id' => $s->id, 'day_of_week' => $s->day_of_week, 'starts_at' => substr($s->starts_at, 0, 5),
                    'ends_at' => substr($s->ends_at, 0, 5), 'room' => $s->room,
                ]),
            ]),
            'canMessage' => request()->user()->hasPermission('mensajes.usar'),
            'options' => [
                'teachers' => Options::teachers(),
                'subjects' => collect(Options::subjects())->filter(fn ($s) => ! $s['grade_id'] || $s['grade_id'] === $group->grade_id)->values(),
                'days' => Options::from(Schedule::DAYS),
            ],
        ]);
    }

    private function groupHeader(Group $group): array
    {
        return [
            'id' => $group->id, 'label' => $group->label, 'shift' => $group->shift, 'capacity' => $group->capacity,
            'campus' => $group->campus?->name, 'school_year' => $group->schoolYear?->name,
            'tutor' => $group->tutor?->display_name, 'grade_id' => $group->grade_id, 'grade' => $group->grade?->name, 'level' => $group->grade?->level,
            'classes_count' => $group->classes_count ?? $group->classes()->count(),
            'break' => $group->campus()->first()?->breakFor($group->shift),
        ];
    }

    /**
     * Alumnos que pueden inscribirse al grupo: del plantel activo, aspirantes o activos
     * y sin una inscripción vigente (pendiente o autorizada) en el ciclo del grupo.
     */
    private function candidates(Group $group): array
    {
        $taken = Enrollment::where('school_year_id', $group->school_year_id)->whereIn('status', ['pendiente', 'autorizada'])->pluck('student_id');

        return Student::whereIn('status', ['aspirante', 'activo'])->whereNotIn('id', $taken)
            ->orderBy('paternal_surname')->orderBy('maternal_surname')->orderBy('name')->get()
            ->map(fn ($s) => [
                'value' => $s->id, 'label' => $s->listName() . ' · ' . $s->enrollment_number,
                'status' => $s->status, 'enrollment_number' => $s->enrollment_number,
            ])->all();
    }

    public function storeClass(Request $request, Group $group)
    {
        $data = $request->validate([
            'subject_id' => ['required', Rule::exists('subjects', 'id')->where('institution_id', Tenant::id()),
                Rule::unique('group_subjects')->where('group_id', $group->id)->whereNull('deleted_at')],
            'teacher_id' => ['nullable', new Visible(\App\Models\Teacher::class)],
        ], ['subject_id.unique' => 'La asignatura ya está asignada a este grupo.']);

        // Si se había quitado (eliminación lógica) se recupera en lugar de duplicarla.
        $old = GroupSubject::onlyTrashed()->where('group_id', $group->id)->where('subject_id', $data['subject_id'])->first();
        if ($old) {
            $old->restore();
            $old->update(['teacher_id' => $data['teacher_id'] ?? null]);
        } else {
            $group->classes()->create($data);
        }

        return back()->with('success', 'Asignatura agregada al grupo.');
    }

    public function updateClass(Request $request, GroupSubject $class)
    {
        $data = $request->validate([
            'teacher_id' => ['nullable', new Visible(\App\Models\Teacher::class)],
        ]);
        $class->update($data);

        return back()->with('success', 'Docente asignado.');
    }

    public function destroyClass(GroupSubject $class)
    {
        if ($class->assignments()->exists() || \App\Models\Attendance::where('group_subject_id', $class->id)->exists()) {
            return back()->with('error', 'La clase ya tiene asistencias o tareas registradas; no se puede quitar.');
        }
        $class->delete();

        return back()->with('success', 'Asignatura retirada del grupo.');
    }

    public function storeSchedule(Request $request, GroupSubject $class)
    {
        $data = $request->validate([
            'day_of_week' => 'required|integer|between:1,7',
            'starts_at' => 'required|date_format:H:i',
            'ends_at' => 'required|date_format:H:i|after:starts_at',
            'room' => 'nullable|string|max:40',
        ]);

        // No se programan clases durante el receso del turno del grupo (configurado por plantel).
        $group = $class->group()->with('campus')->first();
        if ($br = $group?->campus?->breakFor($group->shift)) {
            if ($data['starts_at'] < $br['end'] && $data['ends_at'] > $br['start']) {
                return back()->with('error', "El horario se empalma con el receso del turno {$group->shift} ({$br['start']}–{$br['end']}).");
            }
        }

        // Evitar empalmes en el grupo y para el docente.
        $overlap = fn ($q) => $q->where('day_of_week', $data['day_of_week'])
            ->where('starts_at', '<', $data['ends_at'])->where('ends_at', '>', $data['starts_at']);

        $groupClash = Schedule::whereHas('groupSubject', fn ($q) => $q->where('group_id', $class->group_id))->where($overlap)->exists();
        $teacherClash = $class->teacher_id && Schedule::whereHas('groupSubject', fn ($q) => $q->where('teacher_id', $class->teacher_id))->where($overlap)->exists();

        if ($groupClash || $teacherClash) {
            return back()->with('error', $groupClash ? 'El horario se empalma con otra clase del grupo.' : 'El docente ya tiene clase en ese horario.');
        }

        $class->schedules()->create($data);

        return back()->with('success', 'Horario agregado.');
    }

    /**
     * Guarda el horario completo del grupo (armador visual): reemplaza los horarios de sus clases.
     * Valida que no haya dos clases en la misma franja, que no caigan en el receso del turno
     * y que el docente no tenga otra clase a la misma hora en otro grupo.
     */
    public function saveSchedule(Request $request, Group $group)
    {
        $data = $request->validate([
            'entries' => 'present|array',
            'entries.*.class_id' => 'required|integer',
            'entries.*.day' => 'required|integer|between:1,7',
            'entries.*.starts_at' => 'required|date_format:H:i',
            'entries.*.ends_at' => 'required|date_format:H:i',
            'entries.*.room' => 'nullable|string|max:40',
        ]);
        $classes = $group->classes()->with('subject:id,name', 'teacher')->get()->keyBy('id');
        $entries = collect($data['entries']);
        $days = Schedule::DAYS;
        $errors = [];

        foreach ($entries as $i => $e) {
            if (! $classes->has($e['class_id'])) {
                return back()->with('error', 'Una de las asignaturas no pertenece a este grupo.');
            }
            if ($e['ends_at'] <= $e['starts_at']) {
                $errors[] = "{$days[$e['day']]} {$e['starts_at']}: la hora de fin debe ser posterior al inicio.";
            }
            foreach ($entries->slice($i + 1) as $o) {
                if ($o['day'] === $e['day'] && $o['starts_at'] < $e['ends_at'] && $o['ends_at'] > $e['starts_at']) {
                    $errors[] = "{$days[$e['day']]} {$e['starts_at']}: hay dos clases en la misma franja.";
                }
            }
        }
        if ($br = $group->campus()->first()?->breakFor($group->shift)) {
            foreach ($entries as $e) {
                if ($e['starts_at'] < $br['end'] && $e['ends_at'] > $br['start']) {
                    $errors[] = "{$days[$e['day']]} {$e['starts_at']}: cae en el receso ({$br['start']}–{$br['end']}).";
                }
            }
        }
        // Choques del docente con sus clases de otros grupos
        $teacherIds = $classes->pluck('teacher_id')->filter()->unique();
        $others = Schedule::with('groupSubject.group', 'groupSubject.subject')
            ->whereHas('groupSubject', fn ($q) => $q->whereIn('teacher_id', $teacherIds)->where('group_id', '!=', $group->id))->get();
        foreach ($entries as $e) {
            $t = $classes[$e['class_id']]->teacher_id;
            if (! $t) continue;
            $clash = $others->first(fn ($o) => $o->groupSubject->teacher_id === $t && (int) $o->day_of_week === (int) $e['day']
                && substr($o->starts_at, 0, 5) < $e['ends_at'] && substr($o->ends_at, 0, 5) > $e['starts_at']);
            if ($clash) {
                $errors[] = "{$days[$e['day']]} {$e['starts_at']}: {$classes[$e['class_id']]->teacher?->display_name} ya tiene {$clash->groupSubject->subject?->name} con el grupo {$clash->groupSubject->group?->label}.";
            }
        }
        if ($errors) {
            return back()->with('error', 'No se guardó el horario: ' . implode(' ', array_slice(array_unique($errors), 0, 4)));
        }

        DB::transaction(function () use ($classes, $entries) {
            Schedule::whereIn('group_subject_id', $classes->keys())->delete(); // eliminación lógica
            foreach ($entries as $e) {
                Schedule::create(['group_subject_id' => $e['class_id'], 'day_of_week' => $e['day'], 'starts_at' => $e['starts_at'],
                    'ends_at' => $e['ends_at'], 'room' => $e['room'] ?? null]);
            }
        });

        return back()->with('success', 'Horario del grupo ' . $group->label . ' guardado (' . $entries->count() . ' clases a la semana).');
    }

    public function destroySchedule(Schedule $schedule)
    {
        $schedule->delete();

        return back()->with('success', 'Horario eliminado.');
    }

    /** Lista del grupo en PDF. */
    public function list(Group $group)
    {
        $group->load(['grade', 'schoolYear', 'tutor', 'institution']);
        $students = $group->students()->get();

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.lista-grupo', compact('group', 'students'))
            ->download('lista-' . \Illuminate\Support\Str::slug($group->label) . '.pdf');
    }

    /** Helper para formatear el grupo y no repetir código */
    private function formatGroup(Group $g)
    {
        return [
            ...$g->only(['id', 'name', 'shift', 'capacity', 'campus_id', 'grade_id', 'tutor_teacher_id', 'school_year_id']),
            'label' => $g->label,
            'grade' => $g->grade?->name,
            'campus' => $g->campus?->name,
            'tutor' => $g->tutor?->display_name,
            'level' => $g->grade?->level,
            'students_count' => $g->students_count,
            'classes_count' => $g->classes_count,
        ];
    }

    private function validated(Request $request): array
    {
        $inst = Tenant::id();
        return $request->validate([
            'school_year_id' => ['required', Rule::exists('school_years', 'id')->where('institution_id', $inst)],
            'grade_id' => ['required', Rule::exists('grades', 'id')->where('institution_id', $inst)],
            'tutor_teacher_id' => ['nullable', new Visible(\App\Models\Teacher::class)],
            'name' => 'required|string|max:30',
            'shift' => 'required|in:matutino,vespertino,nocturno,mixto',
            'capacity' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);
    }
}