<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Inertia\Inertia;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /** Mensajes por defecto de las páginas de error. */
    private const MESSAGES = [
        403 => 'No tienes permiso para acceder a esta sección.',
        404 => 'La página que buscas no existe o fue movida.',
        500 => 'Ocurrió un error inesperado. Intenta de nuevo en unos minutos.',
        503 => 'El sistema está en mantenimiento. Vuelve en unos minutos.',
    ];

    /**
     * Errores de navegación (403, 404, 500, 503) se muestran con la página de error de SIGUE
     * en lugar de la pantalla negra de Laravel. Las peticiones JSON conservan su respuesta.
     */
    public function render($request, Throwable $e)
    {
        $response = parent::render($request, $e);
        $status = $response->getStatusCode();

        if ($status === 419) {
            return back()->with('error', 'La sesión expiró por inactividad. Intenta de nuevo.');
        }
        if ($request->expectsJson() || ! isset(self::MESSAGES[$status]) || ($status === 500 && config('app.debug'))) {
            return $response;
        }

        $custom = $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface ? trim($e->getMessage()) : '';
        // Mensajes técnicos en inglés de Laravel/Symfony no se muestran al usuario
        $message = $custom !== '' && ! preg_match('/^(This action is unauthorized|Not Found|Forbidden|Unauthorized|The route)/i', $custom)
            ? $custom : self::MESSAGES[$status];

        return Inertia::render('Error', [
            'status' => $status,
            'message' => $message,
            'home' => $request->user() ? route($request->user()->homeRoute()) : route('login'),
        ])->toResponse($request)->setStatusCode($status);
    }
}
