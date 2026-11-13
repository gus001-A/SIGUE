<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ResetPasswordController extends Controller
{
    public function showResetForm(Request $request, string $token)
    {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'token' => 'required',
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);
        $email = mb_strtolower($data['email']);

        $row = DB::table('password_reset_tokens')->where('email', $email)->first();
        if (! $row || ! Hash::check($data['token'], $row->token) || Carbon::parse($row->created_at)->addMinutes(60)->isPast()) {
            throw ValidationException::withMessages(['email' => 'El enlace no es válido o ya expiró. Solicita uno nuevo.']);
        }

        $user = User::where('email', $email)->firstOrFail();
        $user->update(['password' => $data['password'], 'must_change_password' => false]);
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        return redirect()->route('login')->with('success', 'Contraseña actualizada. Ya puedes iniciar sesión.');
    }
}
