<?php

namespace App\Http\Controllers;

use App\Models\CodigoPostal;
use Illuminate\Http\JsonResponse;

/** Autocompletado de domicilio: con el CP devuelve estado, municipio y colonias. */
class PostalCodeController extends Controller
{
    public function show(string $cp): JsonResponse
    {
        if (! preg_match('/^\d{5}$/', $cp)) {
            return response()->json(['found' => false, 'message' => 'El código postal debe tener 5 dígitos.'], 422);
        }

        try {
            $data = CodigoPostal::buscar($cp);
        } catch (\Throwable $e) {
            // Catálogo aún sin cargar: se puede capturar a mano.
            $data = null;
        }

        return $data
            ? response()->json([
                'found' => true, 'cp' => $data['cp'], 'state' => $data['estado'], 'municipality' => $data['municipio'],
                'city' => $data['ciudad'], 'colonies' => $data['colonias'],
            ])
            : response()->json(['found' => false, 'message' => 'No encontramos ese código postal; captura el domicilio a mano.']);
    }
}
