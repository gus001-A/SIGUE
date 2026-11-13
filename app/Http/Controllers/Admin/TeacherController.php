<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GroupSubject;
use App\Models\Role;
use App\Models\Teacher;
use App\Models\User;
use App\Support\Options;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TeacherController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search'));
        $status = $request->query('status');

        $items = Teacher::with(['campus:id,name', 'user:id,email,last_login_at', 'classes.subject:id,name', 'classes.group.grade'])
            ->withCount('classes')
            ->when($search, fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")
                ->orWhere('paternal_surname', 'like', "%{$search}%")->orWhere('employee_number', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderBy('paternal_surname')->get()
            ->map(fn ($t) => [
                ...$t->only(['id', 'employee_number', 'name', 'paternal_surname', 'maternal_surname', 'email', 'phone', 'curp', 'rfc',
                    'specialty', 'status', 'campus_id', 'gender', 'degree', 'career', 'alma_mater', 'professional_license']),
                'full_name' => $t->full_name,
                'display_name' => $t->display_name,
                'title' => $t->title,
                'degree_label' => Teacher::DEGREES[$t->degree][0] ?? null,
                // Materias que imparte (según sus clases asignadas) con los grupos de cada una
                'subjects' => $t->classes->groupBy(fn ($c) => $c->subject?->name ?? 'Sin materia')
                    ->map(fn ($cs, $name) => ['name' => $name, 'groups' => $cs->map(fn ($c) => $c->group?->label)->filter()->sort()->values()])->values(),
                'hired_on' => $t->hired_on?->toDateString(),
                'campus' => $t->campus?->name,
                'classes_count' => $t->classes_count,
                'has_account' => (bool) $t->user_id,
                'last_login' => $t->user?->last_login_at?->diffForHumans(),
            ]);

        return Inertia::render('Admin/Docentes/Index', [
            'items' => $items,
            'filters' => ['search' => $search, 'status' => $status],
            'options' => [
                'campuses' => Options::campuses(),
                'degrees' => collect(Teacher::DEGREES)->map(fn ($d, $k) => ['value' => $k, 'label' => $d[0]])->values(),
            ],
            'stats' => [
                'total' => Teacher::count(),
                'activos' => Teacher::where('status', 'activo')->count(),
                'sin_clases' => Teacher::where('status', 'activo')->doesntHave('classes')->count(),
            ],
        ]);
    }

    public function show(Teacher $teacher)
    {
        $classes = GroupSubject::with(['group.grade', 'subject:id,name', 'schedules'])->where('teacher_id', $teacher->id)->get();

        return response()->json([
            'teacher' => $teacher->full_name,
            'classes' => $classes->map(fn ($c) => [
                'id' => $c->id, 'subject' => $c->subject?->name, 'group' => $c->group?->label,
                'hours' => $c->schedules->count(),
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $password = null;
        DB::transaction(function () use ($data, $request, &$password) {
            $teacher = Teacher::create($data);
            if ($request->boolean('create_account') && $teacher->email) {
                $password = $this->createAccount($teacher);
            }
        });

        return back()->with('success', 'Docente registrado.' . ($password ? " Contraseña temporal: {$password}" : ''));
    }

    public function update(Request $request, Teacher $teacher)
    {
        $data = $this->validated($request, $teacher);
        $teacher->update($data);
        if ($teacher->user) {
            $teacher->user->update(['name' => $teacher->name, 'paternal_surname' => $teacher->paternal_surname,
                'maternal_surname' => $teacher->maternal_surname, 'is_active' => $teacher->status === 'activo']);
        }

        $password = null;
        if ($request->boolean('create_account') && ! $teacher->user_id && $teacher->email) {
            $password = $this->createAccount($teacher);
        }

        return back()->with('success', 'Docente actualizado.' . ($password ? " Contraseña temporal: {$password}" : ''));
    }

    public function destroy(Teacher $teacher)
    {
        if ($teacher->classes()->exists()) {
            $teacher->update(['status' => 'inactivo']);
            $teacher->user?->update(['is_active' => false]);
            return back()->with('warning', 'El docente tiene clases asignadas: se marcó como inactivo.');
        }
        $teacher->delete();

        return back()->with('success', 'Docente eliminado.');
    }

    private function createAccount(Teacher $teacher): string
    {
        $password = Str::password(10, symbols: false);
        $user = User::firstOrNew(['email' => mb_strtolower($teacher->email)]);
        abort_if($user->exists && $user->institution_id !== Tenant::id(), 422, 'El correo ya pertenece a otra institución.');
        $user->fill([
            'institution_id' => Tenant::id(), 'campus_id' => $teacher->campus_id, 'name' => $teacher->name,
            'paternal_surname' => $teacher->paternal_surname, 'maternal_surname' => $teacher->maternal_surname,
            'phone' => $teacher->phone, 'password' => $password, 'must_change_password' => true, 'is_active' => true,
        ])->save();
        $user->roles()->syncWithoutDetaching([Role::where('slug', Role::DOCENTE)->value('id')]);
        $teacher->update(['user_id' => $user->id]);
        return $password;
    }

    private function validated(Request $request, ?Teacher $teacher = null): array
    {
        $data = $request->validate([
            'employee_number' => 'nullable|string|max:30',
            'name' => 'required|string|max:120',
            'paternal_surname' => 'required|string|max:120',
            'maternal_surname' => 'nullable|string|max:120',
            'email' => ['nullable', 'email', Rule::unique('teachers')->where('institution_id', Tenant::id())->whereNull('deleted_at')->ignore($teacher?->id)],
            'phone' => ['nullable', 'digits:10'],
            'curp' => ['nullable', 'string', 'size:18', 'regex:/^[A-Z]{4}\d{6}[HMX][A-Z]{5}[A-Z0-9]\d$/i'],
            'rfc' => ['nullable', 'string', 'regex:/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/iu'],
            'specialty' => 'nullable|string|max:150',
            'gender' => 'nullable|in:F,M,X',
            'degree' => ['nullable', Rule::in(array_keys(Teacher::DEGREES))],
            'career' => 'nullable|string|max:180',
            'alma_mater' => 'nullable|string|max:180',
            'professional_license' => 'nullable|string|max:20',
            'hired_on' => 'nullable|date',
            'status' => 'required|in:activo,inactivo,licencia',
        ]);
        foreach (['curp', 'rfc'] as $k) {
            if (! empty($data[$k])) {
                $data[$k] = strtoupper($data[$k]);
            }
        }
        if (! empty($data['email'])) {
            $data['email'] = mb_strtolower($data['email']);
        }
        return $data;
    }
}
