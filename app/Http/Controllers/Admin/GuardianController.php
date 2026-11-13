<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Support\Options;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Padres o tutores (MOD-TUT-001): registro, validación de identidad y vinculación. */
class GuardianController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search'));

        $items = Guardian::with(['students:id,name,paternal_surname,maternal_surname', 'user:id,last_login_at'])
            ->when($search, fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")
                ->orWhere('paternal_surname', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhereHas('students', fn ($s) => $s->where('students.name', 'like', "%{$search}%")->orWhere('students.paternal_surname', 'like', "%{$search}%"))))
            ->orderBy('paternal_surname')->get()
            ->map(fn ($g) => [
                ...$g->only(['id', 'name', 'paternal_surname', 'maternal_surname', 'email', 'phone', 'curp', 'occupation', 'address', 'postal_code', 'colony', 'municipality', 'state', 'location_consent']),
                'full_name' => $g->full_name,
                'verified' => (bool) $g->identity_verified_at,
                'has_account' => (bool) $g->user_id,
                'last_login' => $g->user?->last_login_at?->diffForHumans(),
                'students' => $g->students->map(fn ($s) => ['id' => $s->id, 'name' => $s->full_name,
                    'relationship' => Guardian::RELATIONSHIPS[$s->pivot->relationship] ?? $s->pivot->relationship,
                    'relationship_key' => $s->pivot->relationship, 'is_primary' => (bool) $s->pivot->is_primary]),
            ]);

        return Inertia::render('Admin/Tutores/Index', [
            'items' => $items,
            'filters' => ['search' => $search],
            'stats' => [
                'total' => Guardian::count(),
                'con_cuenta' => Guardian::whereNotNull('user_id')->count(),
                'sin_verificar' => Guardian::whereNull('identity_verified_at')->count(),
            ],
            'options' => [
                'relationships' => Options::from(Guardian::RELATIONSHIPS),
                'students' => Student::where('status', '!=', 'baja')->orderBy('paternal_surname')->get()
                    ->map(fn ($s) => ['value' => $s->id, 'label' => $s->listName() . ' · ' . $s->enrollment_number]),
            ],
            'canEdit' => $request->user()->hasPermission('tutores.editar'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $password = null;

        DB::transaction(function () use ($data, $request, &$password) {
            $g = Guardian::create($data);
            $this->syncStudents($request, $g);
            if ($request->boolean('create_account') && $g->email) {
                $password = $this->createAccount($g);
            }
        });

        return back()->with('success', 'Tutor registrado.' . ($password ? " Contraseña temporal: {$password}" : ''));
    }

    public function update(Request $request, Guardian $guardian)
    {
        $guardian->update($this->validated($request, $guardian));
        $this->syncStudents($request, $guardian);

        $password = null;
        if ($request->boolean('create_account') && ! $guardian->user_id && $guardian->email) {
            $password = $this->createAccount($guardian);
        }

        return back()->with('success', 'Tutor actualizado.' . ($password ? " Contraseña temporal: {$password}" : ''));
    }

    public function verify(Guardian $guardian)
    {
        $guardian->update(['identity_verified_at' => $guardian->identity_verified_at ? null : now()]);

        return back()->with('success', $guardian->identity_verified_at ? 'Identidad validada.' : 'Validación retirada.');
    }

    public function destroy(Guardian $guardian)
    {
        // Eliminación lógica: se conservan sus vínculos con alumnos como historial.
        $guardian->user?->update(['is_active' => false]);
        $guardian->delete();

        return back()->with('success', 'Tutor eliminado.');
    }

    private function syncStudents(Request $request, Guardian $g): void
    {
        if (! $request->has('students')) {
            return;
        }
        $rows = collect($request->input('students', []))
            ->filter(fn ($r) => ! empty($r['id']))
            ->mapWithKeys(fn ($r) => [(int) $r['id'] => [
                'relationship' => in_array($r['relationship'] ?? '', array_keys(Guardian::RELATIONSHIPS), true) ? $r['relationship'] : 'tutor',
                'is_primary' => (bool) ($r['is_primary'] ?? false),
            ]]);
        $valid = Student::whereIn('id', $rows->keys())->pluck('id')->all();
        $g->students()->sync($rows->only($valid)->all());
    }

    private function createAccount(Guardian $g): string
    {
        $password = Str::password(10, symbols: false);
        $user = User::firstOrNew(['email' => mb_strtolower($g->email)]);
        abort_if($user->exists && $user->institution_id !== Tenant::id(), 422, 'El correo ya pertenece a otra institución.');
        // Cada tutor es de un solo plantel: una cuenta de portal no puede ligarse a tutores de dos planteles.
        abort_if($user->exists && Guardian::withoutGlobalScopes()->where('user_id', $user->id)->where('id', '!=', $g->id)->exists(), 422,
            'Ese correo ya tiene una cuenta de portal ligada a un tutor de otro plantel. Usa un correo distinto.');
        $user->fill([
            'institution_id' => Tenant::id(), 'name' => $g->name, 'paternal_surname' => $g->paternal_surname,
            'maternal_surname' => $g->maternal_surname, 'phone' => $g->phone, 'password' => $password,
            'must_change_password' => true, 'is_active' => true,
        ])->save();
        $user->roles()->syncWithoutDetaching([Role::where('slug', Role::TUTOR)->value('id')]);
        $g->update(['user_id' => $user->id]);
        return $password;
    }

    private function validated(Request $request, ?Guardian $guardian = null): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'paternal_surname' => 'required|string|max:120',
            'maternal_surname' => 'nullable|string|max:120',
            'email' => ['nullable', 'email', Rule::unique('guardians')->where('institution_id', Tenant::id())->where('campus_id', $guardian?->campus_id ?? Tenant::campusId())
                ->whereNull('deleted_at')->ignore($guardian?->id)],
            'phone' => ['nullable', 'digits:10'],
            'curp' => ['nullable', 'string', 'size:18', 'regex:/^[A-Z]{4}\d{6}[HMX][A-Z]{5}[A-Z0-9]\d$/i'],
            'occupation' => 'nullable|string|max:120',
            'address' => 'nullable|string|max:255',
            'postal_code' => ['nullable', 'digits:5'],
            'colony' => 'nullable|string|max:120',
            'municipality' => 'nullable|string|max:120',
            'state' => 'nullable|string|max:60',
            'location_consent' => 'boolean',
            'students' => 'nullable|array',
        ]);
        unset($data['students']);
        if (! empty($data['email'])) {
            $data['email'] = mb_strtolower($data['email']);
        }
        if (! empty($data['curp'])) {
            $data['curp'] = strtoupper($data['curp']);
        }
        return $data;
    }
}
