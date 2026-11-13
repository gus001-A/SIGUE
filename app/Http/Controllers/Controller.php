<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /** La app móvil (/app/...) reutiliza los datos de la vista web con su propia pantalla. */
    protected function view(\Illuminate\Http\Request $request, string $web, string $app): string
    {
        return $request->routeIs('app.*') ? $app : $web;
    }
}
