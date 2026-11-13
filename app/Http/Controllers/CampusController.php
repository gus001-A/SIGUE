<?php

namespace App\Http\Controllers;

use App\Models\Campus;
use App\Support\Tenant;
use Illuminate\Http\Request;

/** Selector del plantel con el que se trabaja. */
class CampusController extends Controller
{
    public function switch(Request $request)
    {
        $user = $request->user();
        abort_if($user->campus_id && ! $user->isSuperAdmin(), 403, 'Tu usuario está asignado a un solo plantel.');

        $data = $request->validate(['campus_id' => 'required|integer']);
        $campus = Campus::where('is_active', true)->findOrFail($data['campus_id']);

        $request->session()->put('campus_id', $campus->id);

        // Volver a la misma sección (los detalles de otro plantel ya no aplican).
        $back = url()->previous();
        $section = preg_replace('#^(https?://[^/]+/[^/?]+).*$#', '$1', $back);

        return redirect($section ?: route('home'))->with('success', 'Ahora trabajas en ' . $campus->name . '.');
    }
}
