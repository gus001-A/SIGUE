<?php

namespace App\Http\Middleware;

use App\Models\Message;
use App\Models\Notification;
use App\Models\Role;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();
        $institution = Tenant::institution();

        return [
            ...parent::share($request),

            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->full_name,
                    'first_name' => $user->name,
                    'given_name' => $user->name,
                    'paternal_surname' => $user->paternal_surname,
                    'maternal_surname' => $user->maternal_surname,
                    'phone' => $user->phone,
                    'last_login' => $user->last_login_at?->format('d/m/Y H:i'),
                    'email' => $user->email,
                    // Foto del alumno (si el usuario es alumno) para el avatar del menú
                    'photo_url' => $user->student?->photoUrl(),
                    'roles' => $user->roles->map(fn ($r) => ['slug' => $r->slug, 'name' => $r->name])->values(),
                    'role_label' => $this->roleLabel($user),
                    'is_superadmin' => $user->isSuperAdmin(),
                    'must_change_password' => (bool) $user->must_change_password,
                ] : null,
                'permissions' => $user ? $user->permissionSlugs() : [],
            ],

            'institution' => $institution ? [
                'id' => $institution->id,
                'name' => $institution->name,
                'short_name' => $institution->short_name ?: $institution->name,
                'logo_url' => $institution->logoUrl(),
                'primary_color' => $institution->primary_color,
                'plan' => $institution->plan,
            ] : null,

            // Plantel de trabajo y planteles disponibles para el selector
            'campus' => Tenant::campus() ? ['id' => Tenant::campus()->id, 'name' => Tenant::campus()->name] : null,
            'campuses' => fn () => Tenant::campusId()
                ? \App\Models\Campus::where('is_active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])
                : [],
            'campusLocked' => (bool) ($user?->campus_id && ! $user?->isSuperAdmin()),

            // Docente: clase de trabajo seleccionada (para el menú) y sus clases (para cambiar de clase)
            'teaching' => function () use ($user) {
                if (! $user?->teacher || ! $user->hasPermission('docencia.usar')) {
                    return null;
                }
                $classes = \App\Models\GroupSubject::with(['subject:id,name', 'group.grade'])->where('teacher_id', $user->teacher->id)->get()
                    ->sortBy(fn ($c) => ($c->group?->label ?? '') . ($c->subject?->name ?? ''))->values();
                $current = $classes->firstWhere('id', (int) session('docente.class_id'));

                return [
                    'current' => $current ? ['id' => $current->id, 'subject' => $current->subject?->name, 'group' => $current->group?->label] : null,
                    'section' => session('docente.section', 'asistencia'),
                    'classes' => $classes->map(fn ($c) => ['value' => $c->id, 'label' => ($c->subject?->name ?? 'Clase') . ' · ' . ($c->group?->label ?? '')])->values(),
                ];
            },

            'counters' => function () use ($user) {
                if (! $user) {
                    return ['notifications' => 0, 'messages' => 0];
                }
                return [
                    'notifications' => Notification::where('user_id', $user->id)->whereNull('read_at')->count(),
                    'messages' => Message::where('recipient_id', $user->id)->whereNull('read_at')->count(),
                ];
            },

            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
                'receipt' => fn () => $request->session()->get('receipt'),
            ],

            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
        ];
    }

    private function roleLabel($user): string
    {
        $order = [Role::SUPERADMIN, Role::DIRECTOR, Role::ADMIN, Role::DOCENTE, Role::TUTOR, Role::ALUMNO];
        $roles = $user->roles->sortBy(fn ($r) => array_search($r->slug, $order))->pluck('name');
        return $roles->first() ?? 'Usuario';
    }
}
