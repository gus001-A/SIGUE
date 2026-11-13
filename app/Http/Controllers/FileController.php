<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Document;
use App\Models\Incident;
use App\Models\Institution;
use App\Models\Student;
use App\Models\Submission;
use App\Support\Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Archivos privados servidos por Laravel con verificación de permisos
 * (los documentos nunca quedan en una ruta pública).
 */
class FileController extends Controller
{
    public function logo(Institution $institution)
    {
        return $this->serve($institution->logo_path, 'logo');
    }

    public function photo(Request $request, Student $student)
    {
        abort_unless(Access::canSeeStudent($request->user(), $student), 403);
        return $this->serve($student->photo_path, 'foto');
    }

    public function document(Request $request, Document $document)
    {
        $user = $request->user();
        abort_unless($user->hasPermission('documentos.validar') || $user->hasPermission('alumnos.ver')
            || Access::isOwnOrChild($user, $document->student_id), 403);

        return $this->serve($document->file_path, $document->original_name, $request->boolean('download'));
    }

    public function assignment(Request $request, Assignment $assignment, int $index = 0)
    {
        abort_unless(Access::canSeeClass($request->user(), $assignment->groupSubject), 403);
        $f = $assignment->fileList()[$index] ?? abort(404);

        return $this->serve($f['path'], $f['name'], $request->boolean('download'));
    }

    /** Material de la clase (avisos y material del Classroom). */
    public function material(Request $request, \App\Models\ClassPost $post, int $index = 0)
    {
        abort_unless(Access::canSeeClass($request->user(), $post->groupSubject), 403);
        $f = $post->fileList()[$index] ?? abort(404);

        return $this->serve($f['path'], $f['name'], $request->boolean('download'));
    }

    public function submission(Request $request, Submission $submission, int $index = 0)
    {
        $user = $request->user();
        abort_unless(Access::isOwnOrChild($user, $submission->student_id)
            || Access::canManageClass($user, $submission->assignment->groupSubject), 403);

        $f = $submission->fileList()[$index] ?? abort(404);

        return $this->serve($f['path'], $f['name'], $request->boolean('download'));
    }

    /** Evidencia de una incidencia: personal con acceso a incidencias, su docente o el propio alumno/tutor. */
    public function incident(Request $request, Incident $incident)
    {
        $user = $request->user();
        abort_unless($user->hasPermission('incidencias.ver') || $user->hasPermission('alumnos.ver')
            || ($incident->groupSubject && Access::canManageClass($user, $incident->groupSubject))
            || Access::isOwnOrChild($user, $incident->student_id), 403);

        return $this->serve($incident->evidence_path, $incident->evidenceName(), $request->boolean('download'));
    }

    private function serve(?string $path, ?string $name = null, bool $download = false)
    {
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return $download
            ? Storage::disk('local')->download($path, $name ?? basename($path))
            : Storage::disk('local')->response($path, $name ?? basename($path));
    }
}
