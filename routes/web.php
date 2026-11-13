<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Docente;
use App\Http\Controllers\FileController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Portal\PortalController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Superadmin\InstitutionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| SIGUE · Sistema Integral de Gestión Universal Educativa
|--------------------------------------------------------------------------
*/

Route::get('/', [AuthController::class, 'home'])->name('home');

// ---------------- Autenticación ----------------
Route::middleware('guest')->group(function () {
    Route::get('/login', \App\Http\Controllers\Auth\LoginPageController::class)->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::get('/recuperar-contrasena', fn () => Inertia::render('Auth/ForgotPassword'))->name('password.request');
    Route::post('/recuperar-contrasena', [ForgotPasswordController::class, 'sendResetLinkEmail'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/restablecer-contrasena/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
    Route::post('/restablecer-contrasena', [ResetPasswordController::class, 'reset'])->middleware('throttle:5,1')->name('password.update');
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {

    // ---------------- Comunes a todos los perfiles ----------------
    Route::get('/perfil', [ProfileController::class, 'show'])->name('perfil');
    Route::put('/perfil', [ProfileController::class, 'update'])->name('perfil.update');
    Route::put('/perfil/contrasena', [ProfileController::class, 'password'])->name('perfil.password');

    // Autocompletado de domicilio por código postal (catálogo SEPOMEX)
    Route::get('/codigos-postales/{cp}', [\App\Http\Controllers\PostalCodeController::class, 'show'])
        ->middleware('throttle:120,1')->name('codigos-postales.show');

    Route::prefix('notificaciones')->name('notificaciones.')->group(function () {
        Route::get('/feed', [NotificationController::class, 'feed'])->name('feed');
        Route::post('/leer-todas', [NotificationController::class, 'readAll'])->name('leer-todas');
        Route::post('/{id}/leer', [NotificationController::class, 'read'])->name('leer');
        Route::delete('/{id}', [NotificationController::class, 'destroy'])->name('eliminar');
    });

    Route::prefix('mensajes')->name('mensajes.')->middleware('perm:mensajes.usar')->group(function () {
        Route::get('/', [MessageController::class, 'index'])->name('index');
        Route::post('/', [MessageController::class, 'store'])->name('store');
        Route::get('/conversaciones', [MessageController::class, 'conversations'])->name('conversaciones');
        Route::get('/{message}/feed', [MessageController::class, 'feed'])->name('feed');
        Route::post('/{message}/escribiendo', [MessageController::class, 'typing'])->middleware('throttle:60,1')->name('escribiendo');
        Route::get('/{message}', [MessageController::class, 'show'])->name('show');
        Route::post('/{message}/responder', [MessageController::class, 'reply'])->name('reply');
    });

    // Comentarios del Classroom (docente, alumnos y tutores; el acceso se valida en el controlador)
    Route::post('/classroom/comentarios', [\App\Http\Controllers\ClassroomCommentController::class, 'store'])->middleware('throttle:60,1')->name('classroom.comentarios.store');
    Route::delete('/classroom/comentarios/{comment}', [\App\Http\Controllers\ClassroomCommentController::class, 'destroy'])->name('classroom.comentarios.destroy');

    // Plantel con el que se trabaja
    Route::post('/plantel', [\App\Http\Controllers\CampusController::class, 'switch'])->name('plantel.cambiar');

    // Archivos privados (con verificación de acceso en el controlador)
    Route::prefix('archivos')->name('archivos.')->group(function () {
        Route::get('/logo/{institution}', [FileController::class, 'logo'])->name('logo');
        Route::get('/foto/{student}', [FileController::class, 'photo'])->name('foto');
        Route::get('/documento/{document}', [FileController::class, 'document'])->name('documento');
        Route::get('/tarea/{assignment}/{index?}', [FileController::class, 'assignment'])->whereNumber('index')->name('tarea');
        Route::get('/entrega/{submission}/{index?}', [FileController::class, 'submission'])->whereNumber('index')->name('entrega');
        Route::get('/incidencia/{incident}', [FileController::class, 'incident'])->name('incidencia');
        Route::get('/material/{post}/{index?}', [FileController::class, 'material'])->whereNumber('index')->name('material');
    });

    // ---------------- Administrador general SIGUE ----------------
    Route::prefix('sigue')->name('superadmin.')->middleware('perm:instituciones.gestionar')->group(function () {
        Route::get('/instituciones', [InstitutionController::class, 'index'])->name('instituciones.index');
        Route::post('/instituciones', [InstitutionController::class, 'store'])->name('instituciones.store');
        Route::put('/instituciones/{institution}', [InstitutionController::class, 'update'])->name('instituciones.update');
        Route::post('/instituciones/{institution}/entrar', [InstitutionController::class, 'enter'])->name('instituciones.enter');
        Route::delete('/instituciones/{institution}', [InstitutionController::class, 'destroy'])->name('instituciones.destroy');
        Route::post('/instituciones/{institution}/planteles', [InstitutionController::class, 'storeCampus'])->name('planteles.store');
        Route::put('/planteles/{campus}', [InstitutionController::class, 'updateCampus'])->name('planteles.update');
        Route::delete('/planteles/{campus}', [InstitutionController::class, 'destroyCampus'])->name('planteles.destroy');
        Route::post('/salir-institucion', [InstitutionController::class, 'leave'])->name('instituciones.leave');
    });

    // ---------------- Dashboard institucional ----------------
    Route::get('/dashboard', DashboardController::class)->middleware('perm:tenant|dashboard.ver')->name('dashboard');

    // ---------------- Comunidad escolar ----------------
    Route::prefix('alumnos')->name('alumnos.')->group(function () {
        Route::middleware('perm:tenant|alumnos.ver')->group(function () {
            Route::get('/', [Admin\StudentController::class, 'index'])->name('index');
            Route::get('/{student}', [Admin\StudentController::class, 'show'])->whereNumber('student')->name('show');
            Route::get('/{student}/constancia', [Admin\StudentController::class, 'constancia'])->name('constancia');
        });
        Route::middleware('perm:tenant|alumnos.editar')->group(function () {
            Route::get('/nuevo', [Admin\StudentController::class, 'create'])->name('create');
            Route::get('/importar/plantilla', [Admin\StudentImportController::class, 'template'])->name('importar.plantilla');
            Route::post('/importar/revisar', [Admin\StudentImportController::class, 'preview'])->name('importar.revisar');
            Route::post('/importar', [Admin\StudentImportController::class, 'import'])->name('importar');
            Route::post('/', [Admin\StudentController::class, 'store'])->name('store');
            Route::get('/{student}/editar', [Admin\StudentController::class, 'edit'])->name('edit');
            Route::post('/{student}', [Admin\StudentController::class, 'update'])->name('update'); // POST: admite foto (multipart)
            Route::delete('/{student}', [Admin\StudentController::class, 'destroy'])->name('destroy');
            Route::post('/{student}/cuenta', [Admin\StudentController::class, 'account'])->name('account');
        });
        Route::put('/{student}/salud', [Admin\StudentController::class, 'updateHealth'])->middleware('perm:tenant|alumnos.salud')->name('health');
        Route::post('/{student}/tutores', [Admin\StudentController::class, 'attachGuardian'])->middleware('perm:tenant|tutores.editar')->name('guardians.attach');
        Route::delete('/{student}/tutores/{guardian}', [Admin\StudentController::class, 'detachGuardian'])->middleware('perm:tenant|tutores.editar')->name('guardians.detach');
        // Carga de documentos: personal, el propio alumno o su tutor (se valida en el controlador)
        Route::post('/{student}/documentos', [Admin\DocumentController::class, 'store'])->name('documentos.store');
    });

    Route::prefix('docentes')->name('docentes.')->group(function () {
        Route::get('/', [Admin\TeacherController::class, 'index'])->middleware('perm:tenant|docentes.ver')->name('index');
        Route::get('/{teacher}', [Admin\TeacherController::class, 'show'])->middleware('perm:tenant|docentes.ver')->name('show');
        Route::middleware('perm:tenant|docentes.editar')->group(function () {
            Route::post('/', [Admin\TeacherController::class, 'store'])->name('store');
            Route::put('/{teacher}', [Admin\TeacherController::class, 'update'])->name('update');
            Route::delete('/{teacher}', [Admin\TeacherController::class, 'destroy'])->name('destroy');
        });
    });

    Route::prefix('tutores')->name('tutores.')->group(function () {
        Route::get('/', [Admin\GuardianController::class, 'index'])->middleware('perm:tenant|tutores.ver')->name('index');
        Route::middleware('perm:tenant|tutores.editar')->group(function () {
            Route::post('/', [Admin\GuardianController::class, 'store'])->name('store');
            Route::put('/{guardian}', [Admin\GuardianController::class, 'update'])->name('update');
            Route::post('/{guardian}/verificar', [Admin\GuardianController::class, 'verify'])->name('verify');
            Route::delete('/{guardian}', [Admin\GuardianController::class, 'destroy'])->name('destroy');
        });
    });

    // ---------------- Académico ----------------
    Route::middleware('perm:tenant|academico.gestionar')->group(function () {
        Route::get('/grupos', [Admin\GroupController::class, 'index'])->name('grupos.index');
        Route::post('/grupos', [Admin\GroupController::class, 'store'])->name('grupos.store');
        Route::get('/grupos/{group}', [Admin\GroupController::class, 'show'])->name('grupos.show');
        Route::get('/grupos/{group}/horario', [Admin\GroupController::class, 'schedule'])->name('grupos.horario.ver');
        Route::put('/grupos/{group}', [Admin\GroupController::class, 'update'])->name('grupos.update');
        Route::delete('/grupos/{group}', [Admin\GroupController::class, 'destroy'])->name('grupos.destroy');
        Route::post('/grupos/{group}/clases', [Admin\GroupController::class, 'storeClass'])->name('grupos.clases.store');
        Route::put('/clases/{class}', [Admin\GroupController::class, 'updateClass'])->name('clases.update');
        Route::delete('/clases/{class}', [Admin\GroupController::class, 'destroyClass'])->name('clases.destroy');
        Route::post('/clases/{class}/horarios', [Admin\GroupController::class, 'storeSchedule'])->name('horarios.store');
        Route::put('/grupos/{group}/horario', [Admin\GroupController::class, 'saveSchedule'])->name('grupos.horario');
        Route::delete('/horarios/{schedule}', [Admin\GroupController::class, 'destroySchedule'])->name('horarios.destroy');
    });
    Route::get('/grupos/{group}/lista', [Admin\GroupController::class, 'list'])->middleware('perm:tenant|academico.gestionar|alumnos.ver')->name('grupos.lista');

    Route::get('/incidencias', [Admin\IncidentController::class, 'index'])->middleware('perm:tenant|incidencias.ver')->name('incidencias.index');
    Route::put('/incidencias/{incident}', [Admin\IncidentController::class, 'update'])->middleware('perm:tenant|incidencias.ver')->name('incidencias.update');

    // ---------------- Matriculación ----------------
    Route::prefix('matricula')->name('matricula.')->middleware('perm:tenant|matricula.gestionar')->group(function () {
        Route::get('/', [Admin\EnrollmentController::class, 'index'])->name('index');
        Route::post('/', [Admin\EnrollmentController::class, 'store'])->name('store');
        Route::post('/promover', [Admin\EnrollmentController::class, 'promote'])->name('promote');
        Route::post('/{enrollment}/cambio-grupo', [Admin\EnrollmentController::class, 'changeGroup'])->name('change');
        Route::middleware('perm:matricula.autorizar')->group(function () {
            Route::post('/{enrollment}/autorizar', [Admin\EnrollmentController::class, 'authorizeEnrollment'])->name('authorize');
            Route::post('/{enrollment}/baja', [Admin\EnrollmentController::class, 'drop'])->name('drop');
            Route::post('/grupos/{group}/egreso', [Admin\EnrollmentController::class, 'graduate'])->name('graduate');
            Route::post('/alumnos/{student}/titulacion', [Admin\EnrollmentController::class, 'titulate'])->name('titulate');
        });
    });

    Route::prefix('documentos')->name('documentos.')->group(function () {
        Route::get('/', [Admin\DocumentController::class, 'index'])->middleware('perm:tenant|documentos.validar')->name('index');
        Route::post('/{document}/revision', [Admin\DocumentController::class, 'review'])->middleware('perm:tenant|documentos.validar')->name('review');
        Route::delete('/{document}', [Admin\DocumentController::class, 'destroy'])->name('destroy');
    });

    // ---------------- Administración financiera ----------------
    Route::prefix('finanzas')->name('finanzas.')->group(function () {
        Route::middleware('perm:tenant|finanzas.ver')->group(function () {
            Route::get('/adeudos', [Admin\FinanceController::class, 'charges'])->name('adeudos');
            Route::get('/pagos', [Admin\FinanceController::class, 'payments'])->name('pagos');
            Route::get('/pagos/{payment}/recibo', [Admin\FinanceController::class, 'receipt'])->name('recibo');
            Route::get('/alumnos/{student}/adeudos', [Admin\FinanceController::class, 'studentCharges'])->name('alumno.adeudos');
        });
        Route::middleware('perm:tenant|finanzas.gestionar')->group(function () {
            Route::post('/adeudos', [Admin\FinanceController::class, 'storeCharge'])->name('adeudos.store');
            Route::post('/adeudos/{charge}/cancelar', [Admin\FinanceController::class, 'cancelCharge'])->name('adeudos.cancel');
            Route::post('/pagos', [Admin\FinanceController::class, 'storePayment'])->name('pagos.store');
            Route::post('/pagos/{payment}/cancelar', [Admin\FinanceController::class, 'cancelPayment'])->name('pagos.cancel');
        });
        Route::middleware('perm:tenant|becas.gestionar')->group(function () {
            Route::get('/becas', [Admin\FinanceController::class, 'scholarships'])->name('becas');
            Route::post('/becas', [Admin\FinanceController::class, 'storeScholarship'])->name('becas.store');
            Route::put('/becas/{scholarship}', [Admin\FinanceController::class, 'updateScholarship'])->name('becas.update');
            Route::delete('/becas/{scholarship}', [Admin\FinanceController::class, 'destroyScholarship'])->name('becas.destroy');
        });
    });

    // ---------------- 1 ----------------
    Route::prefix('reportes')->name('reportes.')->middleware('perm:tenant|reportes.ver|reportes.financieros')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/{report}/exportar/{format}', [ReportController::class, 'export'])->name('export');
        Route::get('/{report}', [ReportController::class, 'show'])->name('ver');
    });

    // ---------------- Gestión y configuración ----------------
    Route::prefix('configuracion')->group(function () {
        Route::middleware('perm:tenant|configuracion.gestionar')->group(function () {
            Route::get('/', [Admin\SettingsController::class, 'edit'])->name('configuracion');
            Route::post('/', [Admin\SettingsController::class, 'update'])->name('configuracion.update');
            Route::put('/receso', [Admin\SettingsController::class, 'saveBreaks'])->name('configuracion.receso');
            Route::get('/catalogos/{catalog}', [Admin\CatalogController::class, 'index'])->name('catalogos.index');
            Route::post('/catalogos/{catalog}', [Admin\CatalogController::class, 'store'])->name('catalogos.store');
            Route::put('/catalogos/{catalog}/{id}', [Admin\CatalogController::class, 'update'])->name('catalogos.update');
            Route::delete('/catalogos/{catalog}/{id}', [Admin\CatalogController::class, 'destroy'])->name('catalogos.destroy');
        });
        Route::middleware('perm:tenant|usuarios.gestionar')->group(function () {
            Route::get('/usuarios', [Admin\UserController::class, 'index'])->name('usuarios.index');
            Route::post('/usuarios', [Admin\UserController::class, 'store'])->name('usuarios.store');
            Route::put('/usuarios/{user}', [Admin\UserController::class, 'update'])->name('usuarios.update');
            Route::post('/usuarios/{user}/estado', [Admin\UserController::class, 'toggle'])->name('usuarios.toggle');
            Route::post('/usuarios/{user}/contrasena', [Admin\UserController::class, 'resetPassword'])->name('usuarios.password');
        });
    });

    // ---------------- Módulo docente ----------------
    Route::prefix('docente')->name('docente.')->group(function () {
        Route::get('/', [Docente\TeachingController::class, 'inicio'])->middleware('perm:docencia.usar')->name('inicio');
        Route::get('/clases', [Docente\TeachingController::class, 'clases'])->middleware('perm:docencia.usar')->name('clases');
        Route::get('/tutorias', [Docente\FollowUpController::class, 'tutorias'])->middleware('perm:docencia.usar')->name('tutorias');
        Route::post('/tutorias', [Docente\FollowUpController::class, 'storeTutoria'])->middleware('perm:docencia.usar')->name('tutorias.store');
        Route::put('/tutorias/{session}', [Docente\FollowUpController::class, 'updateTutoria'])->middleware('perm:docencia.usar')->name('tutorias.update');

        // Una clase la gestiona su docente o el personal académico (Access::canManageClass)
        Route::middleware('perm:docencia.usar|academico.gestionar')->group(function () {
            Route::get('/clases/{class}', [Docente\TeachingController::class, 'show'])->name('clase');
            Route::get('/clases/{class}/pase-de-lista', [Docente\TeachingController::class, 'asistencia'])->name('clase.asistencia');
            Route::get('/clases/{class}/classroom', [Docente\TeachingController::class, 'classroom'])->name('clase.classroom');
            Route::get('/clases/{class}/evaluacion', [Docente\TeachingController::class, 'evaluacion'])->name('clase.evaluacion');
            Route::get('/clases/{class}/incidencias', [Docente\TeachingController::class, 'incidencias'])->name('clase.incidencias');
            Route::get('/clases/{class}/alumnos', [Docente\TeachingController::class, 'alumnos'])->name('clase.alumnos');
            Route::post('/clases/{class}/asistencia', [Docente\AttendanceController::class, 'store'])->name('asistencia.store');
            Route::get('/clases/{class}/asistencia/reporte', [Docente\AttendanceController::class, 'report'])->name('asistencia.reporte');
            Route::post('/clases/{class}/actividades', [Docente\ClassroomController::class, 'store'])->name('actividades.store');
            Route::post('/actividades/{assignment}', [Docente\ClassroomController::class, 'update'])->name('actividades.update');
            Route::delete('/actividades/{assignment}', [Docente\ClassroomController::class, 'destroy'])->name('actividades.destroy');
            Route::get('/actividades/{assignment}/entregas', [Docente\ClassroomController::class, 'submissions'])->name('actividades.entregas');
            Route::get('/actividades/{assignment}/entregas/{student}', [Docente\ClassroomController::class, 'work'])->whereNumber('student')->name('actividades.entrega');
            Route::get('/actividades/{assignment}', [Docente\ClassroomController::class, 'show'])->name('actividad');
            Route::post('/actividades/{assignment}/calificar', [Docente\ClassroomController::class, 'grade'])->name('actividades.calificar');
            Route::post('/clases/{class}/publicaciones', [Docente\StreamController::class, 'store'])->name('publicaciones.store');
            Route::post('/clases/{class}/temas', [Docente\TopicController::class, 'store'])->name('temas.store');
            Route::put('/temas/{topic}', [Docente\TopicController::class, 'update'])->name('temas.update');
            Route::delete('/temas/{topic}', [Docente\TopicController::class, 'destroy'])->name('temas.destroy');
            Route::post('/publicaciones/{post}', [Docente\StreamController::class, 'update'])->name('publicaciones.update');
            Route::post('/publicaciones/{post}/fijar', [Docente\StreamController::class, 'pin'])->name('publicaciones.fijar');
            Route::delete('/publicaciones/{post}', [Docente\StreamController::class, 'destroy'])->name('publicaciones.destroy');
            Route::put('/clases/{class}/periodos/{period}/criterios', [Docente\EvaluationController::class, 'saveCriteria'])->name('evaluacion.criterios');
            Route::put('/clases/{class}/periodos/{period}/calificaciones', [Docente\EvaluationController::class, 'saveGrades'])->name('evaluacion.calificaciones');
            Route::post('/clases/{class}/periodos/{period}/publicar', [Docente\EvaluationController::class, 'publish'])->name('evaluacion.publicar');
            Route::post('/clases/{class}/incidencias', [Docente\FollowUpController::class, 'storeIncident'])->name('incidencias.store');
        });
    });

    // ---------------- Portal de alumnos y padres/tutores ----------------
    Route::prefix('portal')->name('portal.')->middleware('perm:portal.alumno|portal.tutor')->group(function () {
        Route::get('/', [PortalController::class, 'inicio'])->name('inicio');
        Route::get('/calificaciones', [PortalController::class, 'calificaciones'])->name('calificaciones');
        Route::get('/clases', [PortalController::class, 'clases'])->name('clases');
        Route::get('/tareas', [PortalController::class, 'tareas'])->name('tareas');
        Route::post('/tareas/{assignment}/entregar', [PortalController::class, 'entregar'])->name('entregar');
        Route::get('/tareas/{assignment}', [PortalController::class, 'tarea'])->name('tarea');
        Route::post('/tareas/{assignment}/anular', [PortalController::class, 'anular'])->name('anular');
        Route::get('/horario', [PortalController::class, 'horario'])->name('horario');
        Route::get('/asistencias', [PortalController::class, 'asistencias'])->name('asistencias');
        Route::get('/incidencias', [PortalController::class, 'incidencias'])->name('incidencias');
        Route::get('/documentos', [PortalController::class, 'documentos'])->name('documentos');
        Route::get('/pagos', [PortalController::class, 'pagos'])->name('pagos');
        Route::get('/pagos/{payment}/recibo', [PortalController::class, 'recibo'])->name('recibo');
    });

    // ---------------- App del docente (solo lo indispensable: hoy, clases, pasar lista y calificar) ----------------
    Route::get('/app/entrar', [\App\Http\Controllers\AppController::class, 'entrar'])->name('app.entrar');
    Route::prefix('app/docente')->name('app.docente.')->middleware('perm:docencia.usar')->group(function () {
        Route::get('/', [Docente\TeachingController::class, 'inicio'])->name('inicio');
        Route::get('/clases', [Docente\TeachingController::class, 'clases'])->name('clases');
        Route::get('/clases/{class}', [Docente\TeachingController::class, 'classroom'])->name('clase');
        Route::get('/clases/{class}/lista', [Docente\TeachingController::class, 'asistencia'])->name('lista');
        Route::get('/actividades/{assignment}', [Docente\ClassroomController::class, 'show'])->name('actividad');
        Route::get('/avisos', [\App\Http\Controllers\AppController::class, 'avisos'])->name('avisos');
        Route::get('/yo', [\App\Http\Controllers\AppController::class, 'yo'])->name('yo');
    });

    // ---------------- App del alumno (vistas tipo app móvil, como Google Classroom) ----------------
    Route::prefix('app')->name('app.')->middleware('perm:portal.alumno|portal.tutor')->group(function () {
        Route::get('/', [PortalController::class, 'clases'])->name('clases');
        Route::get('/pendientes', [PortalController::class, 'tareas'])->name('pendientes');
        Route::get('/tareas/{assignment}', [PortalController::class, 'tarea'])->name('tarea');
        Route::get('/horario', [PortalController::class, 'horario'])->name('horario');
        Route::get('/avisos', [PortalController::class, 'avisos'])->name('avisos');
        Route::get('/yo', [PortalController::class, 'inicio'])->name('perfil');
    });
});
