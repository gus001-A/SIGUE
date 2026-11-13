<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Database\Seeders\DemoInstitutionSeeder;
use Inertia\Inertia;

/** Pantalla de inicio de sesión con accesos rápidos de prueba en desarrollo. */
class LoginPageController extends Controller
{
    /** Una cuenta del seeder por perfil, en el orden en que se usan. */
    private const DEMO = [
        ['superadmin@sigue.mx', 'superadmin', 'Superadministrador'],
        ['director@demo.sigue.mx', 'director', 'Director'],
        ['admin@demo.sigue.mx', 'admin', 'Administrador'],
        ['docente@demo.sigue.mx', 'docente', 'Docente'],
        ['tutor@demo.sigue.mx', 'tutor', 'Padre o tutor'],
        ['alumno@demo.sigue.mx', 'alumno', 'Alumno'],
    ];

    public function __invoke()
    {
        return Inertia::render('Auth/Login', [
            'demo' => $this->showDemo() ? $this->accounts() : [],
        ]);
    }

    /**
     * Por defecto solo en desarrollo Y entrando desde esta computadora, para que un .env con
     * APP_ENV=local subido por error no publique las contraseñas. ACCESOS_DEMO lo fuerza.
     */
    private function showDemo(): bool
    {
        $forced = config('sigue.accesos_demo');
        if ($forced !== null) {
            return (bool) $forced;
        }
        return app()->isLocal() && in_array(request()->getHost(), ['localhost', '127.0.0.1', '::1'], true);
    }

    private function accounts(): array
    {
        $existing = User::whereIn('email', array_column(self::DEMO, 0))->where('is_active', true)->get(['email', 'name', 'paternal_surname'])->keyBy('email');

        return collect(self::DEMO)->filter(fn ($d) => $existing->has($d[0]))->map(fn ($d) => [
            'email' => $d[0], 'role' => $d[1], 'label' => $d[2],
            'name' => trim($existing[$d[0]]->name . ' ' . $existing[$d[0]]->paternal_surname),
            // Contraseña de las cuentas del seeder de demostración (solo se envía en desarrollo).
            'password' => DemoInstitutionSeeder::DEMO_PASSWORD,
        ])->values()->all();
    }
}
