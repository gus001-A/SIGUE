<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::with('roles')->where('email', mb_strtolower($data['email']))->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'El correo o la contraseña son incorrectos.']);
        }
        if (! $user->is_active) {
            throw ValidationException::withMessages(['email' => 'Tu cuenta está desactivada. Contacta a tu institución.']);
        }
        if (! $user->isSuperAdmin() && $user->institution && ! $user->institution->isActive()) {
            throw ValidationException::withMessages(['email' => 'La licencia de tu institución no está activa.']);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $request->session()->forget(['tenant_id', 'campus_id']);

        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->saveQuietly();
        AuditLog::create([
            'institution_id' => $user->institution_id, 'user_id' => $user->id, 'action' => 'login',
            'description' => 'Inicio de sesión', 'ip' => $request->ip(), 'user_agent' => substr((string) $request->userAgent(), 0, 250),
        ]);

        return redirect()->intended(route($user->homeRoute()));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Sesión cerrada correctamente.');
    }

    /** Redirige al inicio que corresponde al rol del usuario. */
    public function home(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }
        // Abierta desde la app instalada (PWA): alumnos y tutores entran a la app tipo Classroom
        if ($request->query('origen') === 'app' && in_array($user->homeRoute(), ['portal.inicio', 'docente.inicio'], true)) {
            return redirect()->route($user->homeRoute() === 'docente.inicio' ? 'app.docente.inicio' : 'app.clases');
        }
        return redirect()->route($user->homeRoute());
    }
}
