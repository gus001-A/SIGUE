<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Rules\Visible;
use App\Models\Attendance;
use App\Models\Charge;
use App\Models\Document;
use App\Models\Enrollment;
use App\Models\GradeRecord;
use App\Models\Guardian;
use App\Models\Incident;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Scholarship;
use App\Models\Student;
use App\Models\User;
use App\Support\Options;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Módulo Alumnos (MOD-ALU-001): expediente personal, académico, salud, finanzas. */
class StudentController extends Controller
{
    public function index(Request $request)
    {
        $f = $request->only(['search', 'status', 'group_id', 'campus_id']);

        $q = Student::with(['currentEnrollment.group.grade', 'campus:id,name'])
            ->when($f['search'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$s}%")->orWhere('paternal_surname', 'like', "%{$s}%")
                ->orWhere('maternal_surname', 'like', "%{$s}%")->orWhere('enrollment_number', 'like', "%{$s}%")
                ->orWhere('curp', 'like', "%{$s}%")
                ->orWhereRaw("CONCAT_WS(' ', name, paternal_surname, maternal_surname) like ?", ["%{$s}%"])))
            ->when($f['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($f['campus_id'] ?? null, fn ($q, $c) => $q->where('campus_id', $c))
            ->when($f['group_id'] ?? null, fn ($q, $g) => $q->whereHas('enrollments', fn ($e) => $e->where('group_id', $g)->where('status', 'autorizada')))
            ->orderBy('paternal_surname')->orderBy('maternal_surname')->orderBy('name');

        return Inertia::render('Admin/Alumnos/Index', [
            'students' => $q->get()->map(fn ($s) => [
                'id' => $s->id,
                'enrollment_number' => $s->enrollment_number,
                'full_name' => $s->full_name,
                'list_name' => $s->listName(),
                'curp' => $s->curp,
                'group' => $s->currentEnrollment?->group?->label,
                'grade' => $s->currentEnrollment?->group?->grade?->name,
                'level' => $s->currentEnrollment?->group?->grade?->level,
                'email' => $s->email,
                'phone' => $s->phone,
                'gender' => $s->gender,
                'campus' => $s->campus?->name,
                'status' => $s->status,
                'has_account' => (bool) $s->user_id,
                'photo_url' => $s->photoUrl(),
            ]),
            'filters' => $f,
            'stats' => [
                'total' => Student::count(),
                'activos' => Student::where('status', 'activo')->count(),
                'aspirantes' => Student::where('status', 'aspirante')->count(),
                'bajas' => Student::where('status', 'baja')->count(),
                'egresados' => Student::whereIn('status', ['egresado', 'titulado'])->count(),
            ],
            'options' => $this->formOptions(),
            'openCreate' => $request->boolean('nuevo'),
        ]);
    }

    public function create()
    {
        return redirect()->route('alumnos.index', ['nuevo' => 1]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $student = DB::transaction(function () use ($data, $request) {
            $data['enrollment_number'] = ($data['enrollment_number'] ?? null) ?: $this->nextEnrollmentNumber();
            $student = Student::create($data);
            $this->savePhoto($request, $student);
            $student->health()->create([]);

            if ($request->filled('group_id')) {
                $group = \App\Models\Group::findOrFail($request->group_id);
                Enrollment::create([
                    'student_id' => $student->id, 'group_id' => $group->id, 'school_year_id' => $group->school_year_id,
                    'type' => 'inscripcion', 'status' => 'pendiente', 'enrolled_on' => now()->toDateString(),
                ]);
            }
            return $student;
        });

        return redirect()->route('alumnos.show', $student)->with('success', 'Alumno registrado. Matrícula: ' . $student->enrollment_number);
    }

    public function show(Request $request, Student $student)
    {
        $user = $request->user();
        $student->load(['campus:id,name', 'guardians.user:id,email', 'health',
            'enrollments.group.grade', 'enrollments.schoolYear:id,name', 'scholarships']);

        $grades = GradeRecord::with(['groupSubject.subject:id,name', 'period:id,name,order', 'criterion:id,name,weight'])
            ->where('student_id', $student->id)->get();

        // Calificaciones por asignatura y periodo (finales publicadas o captura de criterios)
        $calc = $grades->groupBy('group_subject_id')->map(function ($rows) {
            $first = $rows->first();
            return [
                'subject' => $first->groupSubject?->subject?->name,
                'periods' => $rows->groupBy('period_id')->map(fn ($pr) => [
                    'period' => $pr->first()->period?->name,
                    'final' => optional($pr->firstWhere('evaluation_criterion_id', null))->score,
                    'criteria' => $pr->whereNotNull('evaluation_criterion_id')->map(fn ($g) => [
                        'name' => $g->criterion?->name, 'weight' => $g->criterion?->weight, 'score' => $g->score,
                    ])->values(),
                ])->values(),
            ];
        })->values();

        $att = Attendance::where('student_id', $student->id)->orderByDesc('date')->get(['status', 'date']);
        $charges = Charge::where('student_id', $student->id)->orderByDesc('due_on')->get();

        // Inscripción vigente (grupo, grado y nivel reales)
        $currentEnr = $student->enrollments->firstWhere('status', 'autorizada');
        $current = $currentEnr ? [
            'group' => $currentEnr->group?->label, 'group_id' => $currentEnr->group_id, 'grade' => $currentEnr->group?->grade?->name,
            'level' => $currentEnr->group?->grade?->level, 'shift' => $currentEnr->group?->shift, 'school_year' => $currentEnr->schoolYear?->name,
        ] : null;

        // Matriz de calificaciones finales: asignatura × periodo
        $finals = $grades->whereNull('evaluation_criterion_id');
        $periods = $grades->pluck('period')->filter()->unique('id')->sortBy('order')->values();
        $matrix = $grades->groupBy('group_subject_id')->map(function ($rows) use ($periods) {
            $cells = $periods->mapWithKeys(fn ($p) => [$p->id => optional($rows->where('period_id', $p->id)->firstWhere('evaluation_criterion_id', null))->score]);
            $valid = $cells->filter(fn ($v) => $v !== null);
            return [
                'subject' => $rows->first()->groupSubject?->subject?->name,
                'scores' => $cells->values(),
                'average' => $valid->count() ? round($valid->avg(), 2) : null,
            ];
        })->sortBy('subject')->values();

        return Inertia::render('Admin/Alumnos/Show', [
            'student' => [
                ...$student->only(['id', 'enrollment_number', 'name', 'paternal_surname', 'maternal_surname', 'curp', 'gender', 'email',
                    'phone', 'address', 'postal_code', 'colony', 'municipality', 'state', 'emergency_contact_name', 'emergency_contact_phone', 'status', 'user_id', 'photo_publication_consent']),
                'full_name' => $student->full_name,
                'birth_date' => $student->birth_date?->format('d/m/Y'),
                'age' => $student->birth_date?->age,
                'campus' => $student->campus?->name,
                'photo_url' => $student->photoUrl(),
                'graduated_on' => $student->graduated_on?->format('d/m/Y'),
            ],
            'enrollments' => $student->enrollments->map(fn ($e) => [
                'id' => $e->id, 'school_year' => $e->schoolYear?->name, 'group' => $e->group?->label, 'type' => $e->type,
                'status' => $e->status, 'enrolled_on' => $e->enrolled_on?->format('d/m/Y'), 'drop_reason' => $e->drop_reason,
            ]),
            'guardians' => $student->guardians->map(fn ($g) => [
                'id' => $g->id, 'full_name' => $g->full_name, 'email' => $g->email, 'phone' => $g->phone,
                'relationship' => Guardian::RELATIONSHIPS[$g->pivot->relationship] ?? $g->pivot->relationship,
                'is_primary' => (bool) $g->pivot->is_primary, 'can_pickup' => (bool) $g->pivot->can_pickup,
                'has_account' => (bool) $g->user_id,
            ]),
            'health' => $user->hasPermission('alumnos.salud') ? [
                'blood_type' => $student->health?->blood_type, 'rh_factor' => $student->health?->rh_factor,
                'weight_kg' => $student->health?->weight_kg, 'height_cm' => $student->health?->height_cm,
                'allergies' => $student->health?->allergies, 'medical_notes' => $student->health?->medical_notes,
                'consent_at' => $student->health?->consent_at?->format('d/m/Y'), 'consent_by' => $student->health?->consent_by,
            ] : null,
            'grades' => $calc,
            'current' => $current,
            'gradeMatrix' => [
                'periods' => $periods->pluck('name'),
                'rows' => $matrix,
                'average' => $finals->count() ? round($finals->avg('score'), 2) : null,
            ],
            'attendance' => [
                'recent' => $att->take(30)->reverse()->values()->map(fn ($a) => ['date' => $a->date?->format('d/m'), 'status' => $a->status]),
                'total' => $att->count(),
                'presente' => $att->where('status', 'presente')->count(),
                'falta' => $att->where('status', 'falta')->count(),
                'retardo' => $att->where('status', 'retardo')->count(),
                'justificada' => $att->where('status', 'justificada')->count(),
            ],
            'documents' => Document::with('type')->where('student_id', $student->id)->latest()->get()->map->toView(),
            'missingDocuments' => \App\Models\DocumentType::where('is_required', true)
                ->whereNotIn('id', Document::where('student_id', $student->id)->whereIn('status', ['pendiente', 'validado'])->pluck('document_type_id')->filter())
                ->pluck('name'),
            'finance' => $user->hasPermission('finanzas.ver') ? [
                'charges' => $charges->map(fn ($c) => [
                    'id' => $c->id, 'concept' => $c->concept, 'type' => $c->type, 'amount' => $c->amount, 'discount' => $c->discount,
                    'total' => $c->total(), 'balance' => $c->balance(), 'status' => $c->status, 'due_on' => $c->due_on?->format('d/m/Y'),
                    'overdue' => $c->isOverdue(),
                ]),
                'payments' => Payment::where('student_id', $student->id)->latest('paid_at')->get()->map(fn ($p) => [
                    'id' => $p->id, 'folio' => $p->folio, 'amount' => $p->amount, 'method' => Payment::METHODS[$p->method] ?? $p->method,
                    'paid_at' => $p->paid_at?->format('d/m/Y H:i'), 'status' => $p->status, 'concept' => $p->charge?->concept,
                ]),
                'balance' => round($charges->whereIn('status', ['pendiente', 'parcial'])->sum(fn ($c) => $c->balance()), 2),
                'scholarships' => $student->scholarships->map(fn ($s) => [
                    'code' => $s->code, 'name' => $s->name, 'percentage' => $s->percentage, 'amount' => $s->amount,
                    'status' => $s->status, 'ends_on' => $s->ends_on?->format('d/m/Y'),
                ]),
            ] : null,
            'incidents' => Incident::with('teacher:id,name,paternal_surname')->where('student_id', $student->id)->latest('date')->get()->map(fn ($i) => [
                'id' => $i->id, 'date' => $i->date?->format('d/m/Y'), 'category' => Incident::CATEGORIES[$i->category] ?? $i->category,
                'positive' => $i->isPositive(), 'description' => $i->description, 'priority' => $i->priority, 'status' => $i->status,
                'teacher' => $i->teacher?->display_name, 'evidence_url' => $i->evidenceUrl(), 'evidence_name' => $i->evidenceName(),
            ]),
            'options' => [
                'documentTypes' => Options::documentTypes(),
                'relationships' => Options::from(Guardian::RELATIONSHIPS),
                'guardians' => Guardian::orderBy('paternal_surname')->get()->map(fn ($g) => ['value' => $g->id, 'label' => $g->full_name . ($g->email ? " · {$g->email}" : '')]),
            ],
            'form' => [
                ...$student->only(['id', 'enrollment_number', 'name', 'paternal_surname', 'maternal_surname', 'curp', 'gender', 'email',
                    'phone', 'address', 'postal_code', 'colony', 'municipality', 'state', 'emergency_contact_name', 'emergency_contact_phone', 'status',
                    'photo_publication_consent']),
                'birth_date' => $student->birth_date?->toDateString(),
                'photo_url' => $student->photoUrl(),
            ],
            'formOptions' => $this->formOptions(),
            'openEdit' => $request->boolean('editar'),
            'can' => [
                'edit' => $user->hasPermission('alumnos.editar'),
                'health' => $user->hasPermission('alumnos.salud'),
                'guardians' => $user->hasPermission('tutores.editar'),
                'documents' => $user->hasPermission('documentos.validar'),
            ],
        ]);
    }

    public function edit(Student $student)
    {
        return redirect()->route('alumnos.show', ['student' => $student, 'editar' => 1]);
    }

    public function update(Request $request, Student $student)
    {
        $data = $this->validated($request, $student);
        $student->update($data);
        $this->savePhoto($request, $student);

        return redirect()->route('alumnos.show', $student)->with('success', 'Expediente actualizado.');
    }

    public function destroy(Student $student)
    {
        // Baja lógica: la información histórica se conserva (regla 7.2).
        $student->update(['status' => 'baja']);
        $student->delete();

        return redirect()->route('alumnos.index')->with('success', 'Alumno archivado. Su historial se conserva.');
    }

    public function updateHealth(Request $request, Student $student)
    {
        $data = $request->validate([
            'blood_type' => 'nullable|string|max:5',
            'rh_factor' => 'nullable|string|max:10',
            'weight_kg' => 'nullable|numeric|min:0|max:300',
            'height_cm' => 'nullable|numeric|min:0|max:250',
            'allergies' => 'nullable|string|max:2000',
            'medical_notes' => 'nullable|string|max:4000',
            'consent_by' => 'required|string|max:150',
        ]);
        $data['consent_at'] = now();
        $student->health()->updateOrCreate(['student_id' => $student->id], $data);

        return back()->with('success', 'Expediente de salud actualizado.');
    }

    /** Crea o restablece la cuenta de acceso del alumno. */
    public function account(Request $request, Student $student)
    {
        $request->validate(['email' => ['required', 'email', Rule::unique('users', 'email')->ignore($student->user_id)]]);
        $password = Str::password(10, symbols: false);

        $user = $student->user ?? new User(['institution_id' => $student->institution_id]);
        $user->fill([
            'name' => $student->name, 'paternal_surname' => $student->paternal_surname, 'maternal_surname' => $student->maternal_surname,
            'email' => mb_strtolower($request->email), 'password' => $password, 'must_change_password' => true, 'is_active' => true,
        ])->save();
        $user->roles()->syncWithoutDetaching([Role::where('slug', Role::ALUMNO)->value('id')]);
        $student->update(['user_id' => $user->id, 'email' => $user->email]);

        return back()->with('success', "Cuenta lista. Contraseña temporal: {$password}");
    }

    public function attachGuardian(Request $request, Student $student)
    {
        $data = $request->validate([
            'guardian_id' => ['required', new Visible(Guardian::class)],
            'relationship' => ['required', Rule::in(array_keys(Guardian::RELATIONSHIPS))],
            'is_primary' => 'boolean',
            'can_pickup' => 'boolean',
        ]);
        $student->guardians()->syncWithoutDetaching([$data['guardian_id'] => [
            'relationship' => $data['relationship'], 'is_primary' => $data['is_primary'] ?? false, 'can_pickup' => $data['can_pickup'] ?? true,
        ]]);

        return back()->with('success', 'Tutor vinculado.');
    }

    public function detachGuardian(Student $student, Guardian $guardian)
    {
        $student->guardians()->detach($guardian->id);

        return back()->with('success', 'Tutor desvinculado.');
    }

    /** Constancia de estudios en PDF. */
    public function constancia(Student $student)
    {
        $student->load(['currentEnrollment.group.grade', 'currentEnrollment.schoolYear', 'institution']);
        abort_unless($student->currentEnrollment, 422, 'El alumno no tiene una inscripción autorizada.');

        $pdf = Pdf::loadView('pdf.constancia', ['student' => $student, 'institution' => $student->institution, 'enrollment' => $student->currentEnrollment]);

        return $pdf->download('constancia-' . $student->enrollment_number . '.pdf');
    }

    private function formOptions(): array
    {
        return [
            'statuses' => Options::from(Student::STATUSES),
            'groups' => Options::groups(),
        ];
    }

    private function validated(Request $request, ?Student $student = null): array
    {
        $inst = \App\Support\Tenant::id();
        $data = $request->validate([
            'enrollment_number' => ['nullable', 'string', 'max:30', Rule::unique('students')->where('institution_id', $inst)->ignore($student?->id)],
            'name' => 'required|string|max:120',
            'paternal_surname' => 'required|string|max:120',
            'maternal_surname' => 'nullable|string|max:120',
            'birth_date' => 'nullable|date|before:today',
            'curp' => ['nullable', 'string', 'size:18', 'regex:/^[A-Z]{4}\d{6}[HMX][A-Z]{5}[A-Z0-9]\d$/i'],
            'gender' => 'nullable|in:F,M,X',
            'email' => 'nullable|email',
            'phone' => ['nullable', 'digits:10'],
            'address' => 'nullable|string|max:255',
            'postal_code' => ['nullable', 'digits:5'],
            'colony' => 'nullable|string|max:120',
            'municipality' => 'nullable|string|max:120',
            'state' => 'nullable|string|max:60',
            'emergency_contact_name' => 'nullable|string|max:150',
            'emergency_contact_phone' => ['nullable', 'digits:10'],
            'status' => ['required', Rule::in(array_keys(Student::STATUSES))],
            'photo_publication_consent' => 'boolean',
            'photo' => 'nullable|image|max:3072',
            'group_id' => ['nullable', new Visible(\App\Models\Group::class)],
        ], ['curp.regex' => 'La CURP no tiene un formato válido.']);

        if (! empty($data['curp'])) {
            $data['curp'] = strtoupper($data['curp']);
        }
        unset($data['photo'], $data['group_id']);
        return $data;
    }

    private function savePhoto(Request $request, Student $student): void
    {
        if (! $request->hasFile('photo')) {
            return;
        }
        if ($student->photo_path) {
            Storage::disk('local')->delete($student->photo_path);
        }
        $path = $request->file('photo')->store("instituciones/{$student->institution_id}/fotos", 'local');
        $student->update(['photo_path' => $path]);
    }

    private function nextEnrollmentNumber(): string
    {
        $prefix = now()->format('y');
        $last = Student::withTrashed()->withoutGlobalScope('campus')->where('enrollment_number', 'like', $prefix . '%')->max('enrollment_number');
        $n = $last ? ((int) substr($last, 2)) + 1 : 1;
        return $prefix . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
