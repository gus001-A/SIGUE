<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ForgotPasswordController extends Controller
{
    public function sendResetLinkEmail(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $email = mb_strtolower($request->email);
        $user = User::where('email', $email)->first();

        // Respuesta idéntica exista o no la cuenta (no revela correos registrados).
        $ok = back()->with('success', 'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña.');

        if (! $user) {
            return $ok;
        }

        $token = Str::random(64);
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            ['token' => Hash::make($token), 'created_at' => Carbon::now()]
        );

        try {
            $link = route('password.reset', ['token' => $token, 'email' => $email]);
            $html = view('emails.recuperar-contrasena', ['resetLink' => $link, 'nombre' => $user->name])->render();
            Mail::html($html, fn ($m) => $m->to($email)->subject('Restablece tu contraseña | SIGUE'));
        } catch (\Throwable $e) {
            Log::error('Recuperación de contraseña: ' . $e->getMessage());
            return back()->with('error', 'No pudimos enviar el correo. Intenta más tarde.');
        }

        return $ok;
    }
}
