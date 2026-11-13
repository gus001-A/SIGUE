<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Campus;
use App\Models\Institution;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Administración general SIGUE: instituciones, licencias y suscripciones (4.1). */
class InstitutionController extends Controller
{
    public const MODULES = [
        'docentes' => 'Docentes', 'alumnos' => 'Alumnos', 'tutores' => 'Padres o tutores', 'matricula' => 'Matriculación',
        'administracion' => 'Administración y finanzas', 'reportes' => 'Reportes', 'configuracion' => 'Gestión y configuración',
    ];

    public function index(Request $request)
    {
        $search = '';

        $items = Institution::query()->orderBy('name')->get()
            ->map(fn ($i) => [
                'id' => $i->id,
                'name' => $i->name,
                'short_name' => $i->short_name,
                'cct' => $i->cct,
                'email' => $i->email,
                'phone' => $i->phone,
                'address' => $i->address,
                'postal_code' => $i->postal_code,
                'colony' => $i->colony,
                'municipality' => $i->municipality,
                'state' => $i->state,
                'plan' => $i->plan,
                'max_students' => $i->max_students,
                'max_campuses' => $i->max_campuses,
                'modules' => $i->modules ?? array_keys(self::MODULES),
                'license_expires_at' => $i->license_expires_at?->toDateString(),
                'status' => $i->status,
                'active' => $i->isActive(),
                'students' => Student::withoutGlobalScopes()->where('institution_id', $i->id)->where('status', 'activo')->count(),
                'campuses' => Campus::withoutGlobalScopes()->whereNull('deleted_at')->where('institution_id', $i->id)->count(),
                'campus_list' => Campus::withoutGlobalScopes()->whereNull('deleted_at')->where('institution_id', $i->id)->orderBy('name')->get()
                    ->map(fn ($c) => [
                        ...$c->only(['id', 'name', 'code', 'phone', 'address', 'postal_code', 'colony', 'municipality', 'state']),
                        'is_active' => (bool) $c->is_active,
                        'students' => Student::withoutGlobalScopes()->whereNull('deleted_at')->where('campus_id', $c->id)->where('status', 'activo')->count(),
                        'teachers' => \App\Models\Teacher::withoutGlobalScopes()->whereNull('deleted_at')->where('campus_id', $c->id)->count(),
                        'groups' => \App\Models\Group::withoutGlobalScopes()->whereNull('deleted_at')->where('campus_id', $c->id)->count(),
                    ]),
                'license_days' => $i->license_expires_at ? (int) now()->startOfDay()->diffInDays($i->license_expires_at, false) : null,
                'users' => User::where('institution_id', $i->id)->count(),
            ]);

        return Inertia::render('Superadmin/Instituciones', [
            'items' => $items,
            'filters' => ['search' => $search],
            'plans' => collect(Institution::PLANS)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values(),
            'modules' => collect(self::MODULES)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values(),
            'stats' => [
                'institutions' => Institution::count(),
                'active' => Institution::where('status', 'activa')->count(),
                'students' => Student::withoutGlobalScopes()->where('status', 'activo')->count(),
                'users' => User::count(),
            ],
            'current' => session('tenant_id'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data, $request) {
            $inst = Institution::create($data + ['slug' => $this->slug($data['name'])]);
            Campus::create(['institution_id' => $inst->id, 'name' => 'PLANTEL PRINCIPAL', 'is_active' => true]);

            // Administrador institucional inicial
            if ($request->filled('admin_email')) {
                $request->validate([
                    'admin_email' => 'email|unique:users,email',
                    'admin_name' => 'required|string|max:120',
                    'admin_password' => 'required|string|min:8',
                ]);
                $u = User::create([
                    'institution_id' => $inst->id,
                    'name' => $request->admin_name,
                    'email' => mb_strtolower($request->admin_email),
                    'password' => $request->admin_password,
                    'must_change_password' => true,
                ]);
                $u->roles()->sync([Role::where('slug', Role::ADMIN)->value('id')]);
            }
        });

        return back()->with('success', 'Institución registrada.');
    }

    public function update(Request $request, Institution $institution)
    {
        $institution->update($this->validated($request, $institution));
        AuditLog::record('updated', $institution, 'Licencia/suscripción de ' . $institution->name);

        return back()->with('success', 'Institución actualizada.');
    }

    /** Eliminación lógica de la institución (se conserva toda su información). */
    public function destroy(Request $request, Institution $institution)
    {
        abort_if((int) session('tenant_id') === $institution->id, 422, 'Sal de la institución antes de eliminarla.');
        $institution->delete();
        AuditLog::record('deleted', $institution, 'Institución eliminada (lógica): ' . $institution->name);

        return back()->with('success', 'Institución eliminada. Su información se conserva y puede recuperarse.');
    }

    /** Alta de un plantel dentro de una institución. */
    public function storeCampus(Request $request, Institution $institution)
    {
        $count = Campus::withoutGlobalScopes()->whereNull('deleted_at')->where('institution_id', $institution->id)->count();
        if ($institution->max_campuses && $count >= $institution->max_campuses) {
            return back()->with('error', "El plan permite máximo {$institution->max_campuses} plantel(es). Amplía el límite en la licencia.");
        }
        Campus::withoutGlobalScopes()->create($this->campusData($request) + ['institution_id' => $institution->id]);

        return back()->with('success', 'Plantel agregado a ' . $institution->name . '.');
    }

    public function updateCampus(Request $request, int $campus)
    {
        $c = Campus::withoutGlobalScopes()->whereNull('deleted_at')->findOrFail($campus);
        $c->update($this->campusData($request));

        return back()->with('success', 'Plantel actualizado.');
    }

    /** Eliminación lógica del plantel; no se permite si tiene alumnos activos. */
    public function destroyCampus(int $campus)
    {
        $c = Campus::withoutGlobalScopes()->whereNull('deleted_at')->findOrFail($campus);
        $active = Student::withoutGlobalScopes()->whereNull('deleted_at')->where('campus_id', $c->id)->where('status', 'activo')->count();
        if ($active) {
            return back()->with('error', "El plantel tiene {$active} alumno(s) activo(s); dalos de baja o muévelos antes de eliminarlo.");
        }
        $c->delete();

        return back()->with('success', 'Plantel eliminado. Su información se conserva.');
    }

    private function campusData(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'code' => 'nullable|string|max:30',
            'phone' => ['nullable', 'digits:10'],
            'address' => 'nullable|string|max:255',
            'postal_code' => ['nullable', 'digits:5'],
            'colony' => 'nullable|string|max:120',
            'municipality' => 'nullable|string|max:120',
            'state' => 'nullable|string|max:60',
            'is_active' => 'boolean',
        ]);
        $data['name'] = mb_strtoupper($data['name']);
        $data['is_active'] = $request->boolean('is_active', true);
        return $data;
    }

    /** Opera dentro de una institución (soporte). */
    public function enter(Request $request, Institution $institution)
    {
        $campusId = $request->integer('campus_id') ?: null;
        if ($campusId) {
            abort_unless(Campus::withoutGlobalScopes()->where('institution_id', $institution->id)->whereKey($campusId)->exists(), 404);
        }
        $request->session()->put('tenant_id', $institution->id);
        $request->session()->put('campus_id', $campusId);
        AuditLog::create(['institution_id' => $institution->id, 'user_id' => $request->user()->id, 'action' => 'soporte',
            'description' => 'Ingreso de soporte SIGUE', 'ip' => $request->ip()]);

        return redirect()->route('dashboard')->with('info', 'Ahora operas en ' . $institution->name . '.');
    }

    public function leave(Request $request)
    {
        $request->session()->forget(['tenant_id', 'campus_id']);

        return redirect()->route('superadmin.instituciones.index');
    }

    private function validated(Request $request, ?Institution $institution = null): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:180',
            'short_name' => 'nullable|string|max:60',
            'cct' => 'nullable|string|max:30',
            'email' => 'nullable|email',
            'phone' => ['nullable', 'digits:10'],
            'address' => 'nullable|string|max:255',
            'postal_code' => ['nullable', 'digits:5'],
            'colony' => 'nullable|string|max:120',
            'municipality' => 'nullable|string|max:120',
            'state' => 'nullable|string|max:60',
            'plan' => ['required', Rule::in(array_keys(Institution::PLANS))],
            'max_students' => 'nullable|integer|min:1',
            'max_campuses' => 'nullable|integer|min:1',
            'modules' => 'nullable|array',
            'modules.*' => Rule::in(array_keys(self::MODULES)),
            'license_expires_at' => 'nullable|date',
            'status' => ['required', Rule::in(['activa', 'prueba', 'suspendida'])],
        ]);
        return $data;
    }

    private function slug(string $name): string
    {
        $base = Str::slug($name) ?: 'institucion';
        $slug = $base;
        $i = 1;
        while (Institution::where('slug', $slug)->exists()) {
            $slug = $base . '-' . (++$i);
        }
        return $slug;
    }
}
