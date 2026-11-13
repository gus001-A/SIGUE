<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Pantallas comunes de la app móvil del docente: avisos y perfil. */
class AppController extends Controller
{
    /** Entrada a la app: cada quien a su pantalla principal. */
    public function entrar(Request $request)
    {
        $user = $request->user();
        if ($user->hasPermission('docencia.usar')) return redirect()->route('app.docente.inicio');
        if ($user->hasPermission('portal.alumno') || $user->hasPermission('portal.tutor')) return redirect()->route('app.clases');

        return redirect()->route($user->homeRoute());
    }

    public function avisos(Request $request)
    {
        return Inertia::render('App/Avisos', [
            'items' => Notification::where('user_id', $request->user()->id)->latest()->limit(60)->get()->map->toView()->values(),
        ]);
    }

    public function yo(Request $request)
    {
        $user = $request->user();

        return Inertia::render('App/Docente/Yo', [
            'profile' => ['name' => $user->full_name, 'email' => $user->email, 'teacher' => $user->teacher?->display_name],
        ]);
    }
}
