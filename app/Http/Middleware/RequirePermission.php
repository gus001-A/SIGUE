<?php

namespace App\Http\Middleware;

use App\Support\Tenant;
use Closure;
use Illuminate\Http\Request;

/**
 * Uso: ->middleware('perm:alumnos.ver|alumnos.editar')  (basta con uno).
 * Con "tenant" como primer permiso exige además una institución activa
 * (el superadmin debe haber entrado a una institución).
 */
class RequirePermission
{
    public function handle(Request $request, Closure $next, string $permissions)
    {
        $user = $request->user();
        abort_unless($user, 401);

        $list = explode('|', $permissions);

        if (in_array('tenant', $list, true)) {
            $list = array_values(array_diff($list, ['tenant']));
            if (! Tenant::id()) {
                return redirect()->route('superadmin.instituciones.index')
                    ->with('warning', 'Selecciona una institución para continuar.');
            }
        }

        foreach ($list as $perm) {
            if ($user->hasPermission($perm)) {
                return $next($request);
            }
        }

        abort(403, 'No tienes permiso para acceder a esta sección.');
    }
}
