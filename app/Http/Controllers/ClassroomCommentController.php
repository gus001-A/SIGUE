<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\ClassComment;
use App\Models\ClassPost;
use App\Models\Student;
use App\Services\Notifier;
use App\Support\Access;
use App\Support\ClassroomFeed;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Comentarios del Classroom:
 *  - De la clase (públicos): en tareas y avisos; los ven todos los de la clase.
 *  - Privados: en una tarea, solo entre el docente y un alumno (el tutor puede leerlos).
 */
class ClassroomCommentController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['assignment', 'post'])],
            'id' => 'required|integer',
            'body' => 'required|string|max:2000',
            'private' => 'boolean',
            'student_id' => 'nullable|integer',
        ], [], ['body' => 'comentario']);

        $user = $request->user();
        $class = ClassroomFeed::classOf($data['type'], $data['id']) ?? abort(404);
        abort_unless(Access::canSeeClass($user, $class), 403);
        $canManage = Access::canManageClass($user, $class);
        $private = $request->boolean('private');

        $studentId = null;
        if ($private) {
            abort_unless($data['type'] === 'assignment', 422, 'Los comentarios privados son de las tareas.');
            if ($canManage) {
                $studentId = (int) ($data['student_id'] ?? 0);
                abort_unless($class->group->students()->where('students.id', $studentId)->exists(), 422, 'Elige al alumno.');
            } else {
                abort_unless($user->student, 403, 'Solo el alumno puede escribir comentarios privados a su docente.');
                $studentId = $user->student->id;
            }
        }

        $c = ClassComment::create([
            'institution_id' => $class->institution_id, 'group_subject_id' => $class->id,
            'commentable_type' => $data['type'], 'commentable_id' => $data['id'], 'user_id' => $user->id,
            'is_private' => $private, 'student_id' => $studentId, 'body' => trim($data['body']),
        ]);
        $this->notify($c, $class, $canManage);

        return response()->json(ClassroomFeed::comment($c->load('author'), $user, $canManage), 201);
    }

    public function destroy(Request $request, ClassComment $comment)
    {
        $user = $request->user();
        $class = \App\Models\GroupSubject::find($comment->group_subject_id);
        abort_unless($comment->user_id === $user->id || ($class && Access::canManageClass($user, $class)), 403);
        $comment->delete(); // eliminación lógica

        return response()->json(['ok' => true]);
    }

    /** Avisa a la otra parte: comentario privado → docente o alumno; comentario de la clase → docente. */
    private function notify(ClassComment $c, $class, bool $byTeacher): void
    {
        $item = $c->commentable_type === 'assignment' ? Assignment::find($c->commentable_id) : ClassPost::find($c->commentable_id);
        $title = $item?->title ?: ($class->subject?->name ?? 'Clase');
        $author = $c->author?->full_name ?? 'Alguien';
        $text = mb_strimwidth($c->body, 0, 120, '…');

        if ($c->is_private && $byTeacher && ($s = Student::find($c->student_id))) {
            Notifier::student($s, 'mensaje', "Comentario privado · {$title}", "{$author}: {$text}", route('portal.tareas'), $c);
        } elseif (! $byTeacher && ($t = $class->teacher?->user_id) && $t !== $c->user_id) {
            Notifier::send($t, 'mensaje', ($c->is_private ? 'Comentario privado' : 'Comentario de la clase') . " · {$title}", "{$author}: {$text}",
                $c->commentable_type === 'assignment' ? route('docente.actividad', $c->commentable_id) : route('docente.clase.classroom', $class->id), $c);
        }
    }
}
