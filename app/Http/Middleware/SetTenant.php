<?php

namespace App\Http\Middleware;

use App\Models\Campus;
use App\Support\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Fija la institución activa de la petición. Los usuarios normales quedan
 * amarrados a su institución; el administrador general SIGUE elige una
 * institución para operar (session tenant_id) o trabaja en modo global.
 */
class SetTenant
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (! $user) {
            Tenant::set(null);
            return $next($request);
        }

        if (! $user->is_active) {
            return $this->kick($request, 'Tu cuenta está desactivada. Contacta a tu institución.');
        }

        if ($user->isSuperAdmin()) {
            Tenant::set($request->session()->get('tenant_id'));
            $this->resolveCampus($request, $user);
            return $next($request);
        }

        Tenant::set($user->institution_id);

        $institution = Tenant::institution();
        if (! $institution || ! $institution->isActive()) {
            return $this->kick($request, 'La licencia de tu institución no está activa. Contacta al administrador.');
        }

        $this->resolveCampus($request, $user);

        return $next($request);
    }

    /**
     * Plantel de trabajo. El personal administrativo trabaja siempre dentro de
     * un plantel (el asignado a su usuario o el que elija en el selector).
     * Docentes, alumnos y tutores no se filtran por plantel: su acceso ya está
     * limitado a sus propias clases e hijos.
     */
    public static function resolveCampus(Request $request, $user): void
    {
        if (! Tenant::id() || ! ($user->isSuperAdmin() || $user->hasPermission('dashboard.ver'))) {
            return;
        }

        $campuses = Campus::withoutGlobalScopes()->where('institution_id', Tenant::id())->where('is_active', true)->orderBy('name')->pluck('id');
        if ($campuses->isEmpty()) {
            return;
        }

        $wanted = $user->campus_id ?: $request->session()->get('campus_id');
        $id = $campuses->contains($wanted) ? (int) $wanted : (int) $campuses->first();
        $request->session()->put('campus_id', $id);
        Tenant::setCampus($id);
    }

    private function kick(Request $request, string $msg)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Tenant::set(null);
        return redirect()->route('login')->with('error', $msg);
    }
}
