<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $u = $request->user();

        return Inertia::render('Perfil', [
            'profile' => [
                'name' => $u->name,
                'paternal_surname' => $u->paternal_surname,
                'maternal_surname' => $u->maternal_surname,
                'email' => $u->email,
                'phone' => $u->phone,
                'last_login_at' => $u->last_login_at?->format('d/m/Y H:i'),
                'roles' => $u->roles->pluck('name'),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $u = $request->user();
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'paternal_surname' => 'nullable|string|max:120',
            'maternal_surname' => 'nullable|string|max:120',
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($u->id)],
            'phone' => ['nullable', 'digits:10'],
        ]);
        $data['email'] = mb_strtolower($data['email']);
        $u->update($data);

        return back()->with('success', 'Perfil actualizado.');
    }

    public function password(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required',
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);
        $u = $request->user();
        if (! Hash::check($data['current_password'], $u->password)) {
            throw ValidationException::withMessages(['current_password' => 'La contraseña actual no es correcta.']);
        }
        $u->update(['password' => $data['password'], 'must_change_password' => false]);

        return back()->with('success', 'Contraseña actualizada.');
    }
}
