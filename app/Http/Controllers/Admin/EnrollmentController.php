<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Rules\Visible;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Support\Options;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Matriculación (MOD-MAT-001): inscripciones, reinscripciones, altas, bajas, cambios y egresos. */
class EnrollmentController extends Controller
{
    public function index(Request $request)
    {
        $yearId = $request->integer('school_year_id') ?: SchoolYear::current()?->id;
        $f = $request->only(['status', 'group_id', 'type', 'search']);

        $items = Enrollment::with(['student:id,name,paternal_surname,maternal_surname,enrollment_number,status', 'group.grade', 'authorizer:id,name,paternal_surname'])
            ->where('school_year_id', $yearId)
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($f['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($f['group_id'] ?? null, fn ($q, $v) => $q->where('group_id', $v))
            ->when($f['search'] ?? null, fn ($q, $s) => $q->whereHas('student', fn ($w) => $w->where('name', 'like', "%{$s}%")
                ->orWhere('paternal_surname', 'like', "%{$s}%")->orWhere('enrollment_number', 'like', "%{$s}%")))
            ->latest('id')->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'student_id' => $e->student_id,
                'student' => $e->student?->full_name,
                'enrollment_number' => $e->student?->enrollment_number,
                'group' => $e->group?->label,
                'group_id' => $e->group_id,
                'grade_id' => $e->group?->grade_id,
                'grade' => $e->group?->grade?->name,
                'type' => $e->type,
                'status' => $e->status,
                'enrolled_on' => $e->enrolled_on?->format('d/m/Y'),
                'authorized_by' => $e->authorizer?->full_name,
                'drop_reason' => $e->drop_reason,
            ]);

        $base = Enrollment::where('school_year_id', $yearId);

        return Inertia::render('Admin/Matricula/Index', [
            'items' => $items,
            'filters' => $f + ['school_year_id' => $yearId],
            'stats' => [
                'autorizadas' => (clone $base)->where('status', 'autorizada')->count(),
                'pendientes' => (clone $base)->where('status', 'pendiente')->count(),
                'bajas' => (clone $base)->where('status', 'baja')->count(),
                'reinscripciones' => (clone $base)->where('type', 'reinscripcion')->count(),
            ],
            'options' => [
                'schoolYears' => Options::schoolYears(),
                'groups' => Options::groups($yearId),
                'statuses' => Options::from(Enrollment::STATUSES),
                'students' => Student::whereIn('status', ['aspirante', 'activo'])->orderBy('paternal_surname')->get()
                    ->map(fn ($s) => ['value' => $s->id, 'label' => $s->listName() . ' · ' . $s->enrollment_number]),
            ],
            'can' => ['authorize' => $request->user()->hasPermission('matricula.autorizar')],
        ]);
    }

    /** Inscripción o reinscripción a un grupo del ciclo. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'student_id' => ['required', new Visible(Student::class)],
            'group_id' => ['required', new Visible(Group::class)],
            'type' => 'required|in:inscripcion,reinscripcion',
            'notes' => 'nullable|string|max:1000',
        ]);
        $group = Group::findOrFail($data['group_id']);

        $exists = Enrollment::where('student_id', $data['student_id'])->where('school_year_id', $group->school_year_id)
            ->whereIn('status', ['pendiente', 'autorizada'])->exists();
        if ($exists) {
            return back()->with('error', 'El alumno ya tiene una inscripción vigente en ese ciclo. Usa "Cambio de grupo".');
        }
        // Con el grupo lleno la inscripción se registra, pero queda pendiente de autorización.
        $full = $group->capacity && $group->enrollments()->where('status', 'autorizada')->count() >= $group->capacity;

        $auto = ! $full && $request->boolean('authorize') && $request->user()->hasPermission('matricula.autorizar');
        Enrollment::create($data + [
            'school_year_id' => $group->school_year_id,
            'status' => $auto ? 'autorizada' : 'pendiente',
            'enrolled_on' => now()->toDateString(),
            'authorized_by' => $auto ? $request->user()->id : null,
            'authorized_at' => $auto ? now() : null,
        ]);
        if ($auto) {
            Student::whereKey($data['student_id'])->where('status', 'aspirante')->update(['status' => 'activo']);
        }

        if ($full) {
            return back()->with('warning', 'El grupo alcanzó su capacidad; la inscripción quedó pendiente de autorización.');
        }

        return back()->with('success', $auto ? 'Inscripción autorizada.' : 'Inscripción registrada; pendiente de autorización.');
    }

    public function authorizeEnrollment(Request $request, Enrollment $enrollment)
    {
        abort_unless($enrollment->status === 'pendiente', 422, 'Solo se autorizan inscripciones pendientes.');
        $enrollment->update(['status' => 'autorizada', 'authorized_by' => $request->user()->id, 'authorized_at' => now()]);
        $enrollment->student()->where('status', 'aspirante')->update(['status' => 'activo']);

        return back()->with('success', 'Inscripción autorizada.');
    }

    public function drop(Request $request, Enrollment $enrollment)
    {
        $data = $request->validate(['drop_reason' => 'required|string|max:255', 'student_status' => 'boolean']);
        $enrollment->update(['status' => 'baja', 'dropped_on' => now()->toDateString(), 'drop_reason' => $data['drop_reason']]);
        if ($request->boolean('student_status')) {
            $enrollment->student->update(['status' => 'baja']);
        }

        return back()->with('success', 'Baja registrada.');
    }

    public function changeGroup(Request $request, Enrollment $enrollment)
    {
        $data = $request->validate([
            'group_id' => ['required', new Visible(Group::class, fn ($q) => $q->where('school_year_id', $enrollment->school_year_id))],
            'reason' => 'nullable|string|max:255',
        ]);
        abort_if((int) $data['group_id'] === $enrollment->group_id, 422, 'Selecciona un grupo diferente.');

        // Solo se cambia a un grupo del mismo grado; el cambio de grado se hace con la reinscripción.
        $target = Group::findOrFail($data['group_id']);
        if ($target->grade_id !== $enrollment->group->grade_id) {
            return back()->with('error', 'Solo puedes cambiar al alumno a un grupo del mismo grado. Para cambiar de grado usa la reinscripción.');
        }

        DB::transaction(function () use ($enrollment, $data, $request) {
            $enrollment->update(['status' => 'concluida', 'dropped_on' => now()->toDateString(), 'drop_reason' => 'Cambio de grupo' . (! empty($data['reason']) ? ': ' . $data['reason'] : '')]);
            Enrollment::create([
                'student_id' => $enrollment->student_id, 'group_id' => $data['group_id'], 'school_year_id' => $enrollment->school_year_id,
                'type' => $enrollment->type, 'status' => 'autorizada', 'enrolled_on' => now()->toDateString(),
                'authorized_by' => $request->user()->id, 'authorized_at' => now(), 'notes' => 'Cambio desde grupo #' . $enrollment->group_id,
            ]);
        });

        return back()->with('success', 'Cambio de grupo aplicado.');
    }

    /**
     * Reinscripción masiva: pasa a los alumnos activos de un grupo al grupo
     * destino de otro ciclo (cambio de grado).
     */
    public function promote(Request $request)
    {
        $data = $request->validate([
            'from_group_id' => ['required', new Visible(Group::class)],
            'to_group_id' => ['required', 'different:from_group_id', new Visible(Group::class)],
        ]);
        $from = Group::findOrFail($data['from_group_id']);
        $to = Group::findOrFail($data['to_group_id']);
        $count = 0;

        DB::transaction(function () use ($from, $to, $request, &$count) {
            foreach ($from->students()->where('students.status', 'activo')->get() as $s) {
                $already = Enrollment::where('student_id', $s->id)->where('school_year_id', $to->school_year_id)->whereIn('status', ['pendiente', 'autorizada'])->exists();
                if ($already) {
                    continue;
                }
                Enrollment::create([
                    'student_id' => $s->id, 'group_id' => $to->id, 'school_year_id' => $to->school_year_id, 'type' => 'reinscripcion',
                    'status' => 'pendiente', 'enrolled_on' => now()->toDateString(),
                ]);
                $count++;
            }
        });

        return back()->with('success', "{$count} reinscripciones registradas (pendientes de autorización).");
    }

    /** Egreso: concluye las inscripciones del grupo y marca a los alumnos como egresados. */
    public function graduate(Request $request, Group $group)
    {
        $n = 0;
        DB::transaction(function () use ($group, &$n) {
            foreach ($group->enrollments()->where('status', 'autorizada')->get() as $e) {
                $e->update(['status' => 'concluida']);
                $e->student->update(['status' => 'egresado', 'graduated_on' => now()->toDateString()]);
                $n++;
            }
        });

        return back()->with('success', "{$n} alumnos marcados como egresados.");
    }

    public function titulate(Student $student)
    {
        abort_unless($student->status === 'egresado', 422, 'Solo se titulan alumnos egresados.');
        $student->update(['status' => 'titulado']);

        return back()->with('success', 'Alumno marcado como titulado.');
    }
}
