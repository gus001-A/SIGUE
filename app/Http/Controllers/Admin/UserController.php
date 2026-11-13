<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Support\Options;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Administración de usuarios de la institución (MOD-CON-001). */
class UserController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search'));
        $role = $request->query('role');

        $items = User::with('roles:id,slug,name', 'campus:id,name')
            ->where('institution_id', Tenant::id())
            ->when(Tenant::campusId(), fn ($q, $c) => $q->where(fn ($w) => $w->whereNull('campus_id')->orWhere('campus_id', $c)))
            ->when($search, fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")
                ->orWhere('paternal_surname', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->when($role, fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('slug', $role)))
            ->orderBy('name')->get()
            ->map(fn ($u) => [
                ...$u->only(['id', 'name', 'paternal_surname', 'maternal_surname', 'email', 'phone', 'is_active', 'campus_id']),
                'full_name' => $u->full_name,
                'roles' => $u->roles->pluck('slug'),
                'role_names' => $u->roles->pluck('name'),
                'campus' => $u->campus?->name,
                'last_login' => $u->last_login_at?->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Admin/Usuarios/Index', [
            'items' => $items,
            'filters' => ['search' => $search, 'role' => $role],
            'options' => [
                'roles' => $this->assignableRoles($request->user()),
                'campuses' => Options::campuses(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $password = $data['password'] ?? Str::password(10, symbols: false);

        $user = User::create([
            ...collect($data)->except(['roles', 'password'])->all(),
            'institution_id' => Tenant::id(),
            'password' => $password,
            'must_change_password' => empty($data['password']),
        ]);
        $user->roles()->sync($this->roleIds($data['roles']));

        return back()->with('success', 'Usuario creado.' . (empty($data['password']) ? " Contraseña temporal: {$password}" : ''));
    }

    public function update(Request $request, User $user)
    {
        $this->ensureSameInstitution($user);
        $data = $this->validated($request, $user);

        $user->update(collect($data)->except(['roles', 'password'])->all()
            + (! empty($data['password']) ? ['password' => $data['password']] : []));
        // No permitir quitarse a uno mismo la administración.
        if ($user->id !== $request->user()->id) {
            $user->roles()->sync($this->roleIds($data['roles']));
        }

        return back()->with('success', 'Usuario actualizado.');
    }

    public function toggle(Request $request, User $user)
    {
        $this->ensureSameInstitution($user);
        abort_if($user->id === $request->user()->id, 422, 'No puedes desactivar tu propia cuenta.');
        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('success', $user->is_active ? 'Usuario activado.' : 'Usuario desactivado.');
    }

    public function resetPassword(User $user)
    {
        $this->ensureSameInstitution($user);
        $password = Str::password(10, symbols: false);
        $user->update(['password' => $password, 'must_change_password' => true]);

        return back()->with('success', "Contraseña restablecida. Temporal: {$password}");
    }

    private function ensureSameInstitution(User $user): void
    {
        abort_unless($user->institution_id === Tenant::id(), 404);
        // No se administran usuarios asignados a otro plantel.
        abort_if(Tenant::campusId() && $user->campus_id && $user->campus_id !== Tenant::campusId(), 403, 'El usuario pertenece a otro plantel.');
    }

    private function assignableRoles(User $actor): array
    {
        return Role::where('slug', '!=', Role::SUPERADMIN)->orderBy('level')->get()
            ->map(fn ($r) => ['value' => $r->slug, 'label' => $r->name, 'description' => $r->description])->all();
    }

    private function roleIds(array $slugs): array
    {
        return Role::whereIn('slug', $slugs)->where('slug', '!=', Role::SUPERADMIN)->pluck('id')->all();
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'paternal_surname' => 'nullable|string|max:120',
            'maternal_surname' => 'nullable|string|max:120',
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => ['nullable', 'digits:10'],
            'campus_id' => ['nullable', Rule::in(array_filter([Tenant::campusId()]))],
            'is_active' => 'boolean',
            'roles' => 'required|array|min:1',
            'roles.*' => Rule::in(Role::where('slug', '!=', Role::SUPERADMIN)->pluck('slug')->all()),
            'password' => 'nullable|string|min:8',
        ]);
        $data['email'] = mb_strtolower($data['email']);
        return $data;
    }
}
