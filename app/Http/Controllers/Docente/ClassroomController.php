<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\EvaluationCriterion;
use App\Models\GroupSubject;
use App\Models\Submission;
use App\Services\Notifier;
use App\Support\Access;
use App\Support\ClassroomFeed;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Classroom (DOC-CLASS-001): actividades, tareas, proyectos, entregas y retroalimentación. */
class ClassroomController extends Controller
{
    public function store(Request $request, GroupSubject $class)
    {
        abort_unless(Access::canManageClass($request->user(), $class), 403);
        $data = $this->validated($request, $class);

        $a = new Assignment($data + ['group_subject_id' => $class->id, 'created_by' => $request->user()->id]);
        $this->attach($request, $a, $class);
        $a->save();

        if ($a->is_published && SettingsController::notifies('tarea')) {
            foreach ($class->group->students()->get() as $s) {
                Notifier::student($s, 'tarea', 'Nueva ' . strtolower(Assignment::TYPES[$a->type]) . ': ' . $a->title,
                    $class->subject->name . ($a->due_at ? ' · entrega ' . $a->due_at->format('d/m/Y H:i') : ''), route('portal.tareas'), $a);
            }
        }

        return back()->with('success', 'Actividad publicada.');
    }

    public function update(Request $request, Assignment $assignment)
    {
        $class = $assignment->groupSubject;
        abort_unless(Access::canManageClass($request->user(), $class), 403);
        $assignment->fill($this->validated($request, $class));
        $this->attach($request, $assignment, $class);
        $assignment->save();

        return back()->with('success', 'Actividad actualizada.');
    }

    public function destroy(Request $request, Assignment $assignment)
    {
        abort_unless(Access::canManageClass($request->user(), $assignment->groupSubject), 403);
        if ($assignment->submissions()->exists()) {
            return back()->with('error', 'La actividad ya tiene entregas; despublícala en lugar de eliminarla.');
        }
        $assignment->delete(); // eliminación lógica: los archivos se conservan

        return back()->with('success', 'Actividad eliminada.');
    }

    /**
     * Vista de una actividad al estilo Google Classroom: instrucciones arriba, lista de alumnos
     * con estado y calificación, y el trabajo del alumno seleccionado para revisar y devolver.
     */
    public function show(Request $request, Assignment $assignment)
    {
        $class = $assignment->groupSubject;
        $data = app(TeachingController::class)->base($request, $class, 'classroom');
        $subs = $assignment->submissions()->get()->keyBy('student_id');
        $assignment->load('criterion:id,name');

        $data['assignment'] = [
            ...$assignment->only(['id', 'type', 'title', 'description', 'max_score', 'is_published', 'period_id', 'topic_id', 'evaluation_criterion_id']),
            'type_label' => Assignment::TYPES[$assignment->type] ?? $assignment->type,
            'due_at' => $assignment->due_at?->format('Y-m-d H:i'), 'due_label' => $assignment->due_at?->isoFormat('ddd D [de] MMM, HH:mm'),
            'overdue' => $assignment->due_at?->isPast(), 'criterion' => $assignment->criterion?->name,
            'files' => $assignment->fileLinks('archivos.tarea'),
            'rubric' => ClassroomFeed::rubric($assignment->rubric),
            'links' => ClassroomFeed::links($assignment->links),
            'created' => $assignment->created_at?->isoFormat('D [de] MMM'),
        ];
        $data['works'] = collect($data['students'])->map(function ($s) use ($subs, $assignment) {
            $sub = $subs->get($s['id']);
            $status = $sub?->status ?? 'asignada';
            if ($sub && $sub->score !== null) $status = 'calificada';
            return $s + [
                'status' => $status, 'submitted' => (bool) $sub?->submitted_at,
                'submitted_at' => $sub?->submitted_at?->isoFormat('D MMM, HH:mm'),
                'late' => $sub && $assignment->due_at && $sub->submitted_at?->gt($assignment->due_at),
                'missing' => ! $sub?->submitted_at && $assignment->due_at?->isPast(),
                'content' => $sub?->content, 'files' => $sub ? $sub->fileLinks('archivos.entrega') : [],
                'score' => $sub?->score, 'feedback' => $sub?->feedback,
                'graded_at' => $sub?->graded_at?->isoFormat('D MMM, HH:mm'),
                'rubric_scores' => $sub?->rubric_scores ?? [],
            ];
        })->values();
        // Comentarios de la clase (todos) y privados (un hilo por alumno)
        $user = $request->user();
        $data['classComments'] = ClassroomFeed::commentsFor('assignment', [$assignment->id], $user, true)->get($assignment->id, collect())->values();
        $data['privateComments'] = ClassroomFeed::commentsFor('assignment', [$assignment->id], $user, true, true)
            ->mapWithKeys(fn ($list, $thread) => [(int) explode(':', $thread)[1] => $list->values()]);

        return Inertia::render($this->view($request, 'Docente/Clase/Actividad', 'App/Docente/Calificar'), $data);
    }

    /** Trabajo de un alumno en una actividad (modal "Ver entrega" del libro de calificaciones). */
    public function work(Request $request, Assignment $assignment, int $student)
    {
        abort_unless(Access::canManageClass($request->user(), $assignment->groupSubject), 403);
        $s = $assignment->groupSubject->group->students()->where('students.id', $student)->firstOrFail();
        $sub = $assignment->submissions()->where('student_id', $s->id)->first();
        $user = $request->user();

        return response()->json([
            'assignment' => [
                'id' => $assignment->id, 'title' => $assignment->title, 'max_score' => $assignment->max_score, 'type' => $assignment->type,
                'rubric' => ClassroomFeed::rubric($assignment->rubric), 'due_label' => $assignment->due_at?->isoFormat('D [de] MMM, HH:mm'),
            ],
            'student' => ['id' => $s->id, 'name' => $s->listName(), 'photo_url' => $s->photoUrl(), 'enrollment_number' => $s->enrollment_number],
            'work' => [
                'submitted' => (bool) $sub?->submitted_at, 'submitted_at' => $sub?->submitted_at?->isoFormat('D [de] MMM, HH:mm'),
                'late' => $sub && $assignment->due_at && $sub->submitted_at?->gt($assignment->due_at),
                'missing' => ! $sub?->submitted_at && (bool) $assignment->due_at?->isPast(),
                'status' => $sub?->status, 'content' => $sub?->content, 'files' => $sub ? $sub->fileLinks('archivos.entrega') : [],
                'score' => $sub?->score, 'feedback' => $sub?->feedback, 'rubric_scores' => $sub?->rubric_scores ?? [],
                'graded_at' => $sub?->graded_at?->isoFormat('D [de] MMM, HH:mm'),
            ],
            'private' => ClassroomFeed::commentsFor('assignment', [$assignment->id], $user, true, true)->get($assignment->id . ':' . $s->id, collect())->values(),
        ]);
    }

    /** Entregas de una actividad (para el panel de revisión). */
    public function submissions(Request $request, Assignment $assignment)
    {
        abort_unless(Access::canManageClass($request->user(), $assignment->groupSubject), 403);
        $students = $assignment->groupSubject->group->students()->get();
        $subs = $assignment->submissions()->get()->keyBy('student_id');

        return response()->json($students->map(function ($s) use ($subs, $assignment) {
            $sub = $subs->get($s->id);
            return [
                'student_id' => $s->id, 'student' => $s->listName(),
                'submission_id' => $sub?->id, 'status' => $sub?->status ?? 'sin_entrega',
                'submitted_at' => $sub?->submitted_at?->format('d/m/Y H:i'),
                'late' => $sub && $assignment->due_at && $sub->submitted_at?->gt($assignment->due_at),
                'content' => $sub?->content, 'files' => $sub ? $sub->fileLinks('archivos.entrega') : [],
                'score' => $sub?->score, 'feedback' => $sub?->feedback,
            ];
        }));
    }

    /** Calificar y retroalimentar (crea el registro si el alumno no entregó: calificación directa). */
    public function grade(Request $request, Assignment $assignment)
    {
        abort_unless(Access::canManageClass($request->user(), $assignment->groupSubject), 403);
        $data = $request->validate([
            'student_id' => 'required|integer',
            'score' => 'nullable|numeric|min:0|max:' . $assignment->max_score,
            'feedback' => 'nullable|string|max:3000',
            'status' => 'nullable|in:calificada,devuelta',
            'rubric_scores' => 'nullable|array',
            'rubric_scores.*' => 'nullable|integer|min:0',
        ]);
        // Con rúbrica, la calificación es la suma de los niveles elegidos
        if ($assignment->rubric && $request->has('rubric_scores')) {
            $scores = array_map(fn ($v) => $v === null ? null : (int) $v, (array) $data['rubric_scores']);
            $data['rubric_scores'] = $scores;
            // La rúbrica solo sugiere: si el docente escribió la calificación directa, se respeta
            if (($data['score'] ?? null) === null && count(array_filter($scores, fn ($v) => $v !== null))) {
                $data['score'] = min($assignment->max_score, ClassroomFeed::rubricScore($assignment->rubric, $scores));
            }
        }
        abort_unless($assignment->groupSubject->group->students()->where('students.id', $data['student_id'])->exists(), 422);

        $sub = Submission::withTrashed()->firstOrNew(['assignment_id' => $assignment->id, 'student_id' => $data['student_id']]);
        if ($sub->trashed()) {
            $sub->restore();
            $sub->fill(['files' => [], 'content' => null, 'submitted_at' => null]);
        }
        $sub->fill([
            'score' => $data['score'], 'feedback' => $data['feedback'] ?? null,
            'rubric_scores' => $data['rubric_scores'] ?? $sub->rubric_scores,
            'status' => $data['status'] ?? ($data['score'] !== null ? 'calificada' : ($sub->status ?: 'entregada')),
            'graded_by' => $request->user()->id, 'graded_at' => now(),
        ])->save();

        if (SettingsController::notifies('calificacion') && $data['score'] !== null) {
            Notifier::student($sub->student, 'calificacion', 'Calificación: ' . $assignment->title,
                'Obtuviste ' . rtrim(rtrim(number_format($sub->score, 2), '0'), '.') . ' de ' . $assignment->max_score, route('portal.tareas'), $sub);
        }

        return $request->wantsJson()
            ? response()->json(['ok' => true, 'score' => $sub->score, 'status' => $sub->status, 'rubric_scores' => $sub->rubric_scores ?? []])
            : back()->with('success', 'Calificación guardada.');
    }

    private function validated(Request $request, GroupSubject $class): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Assignment::TYPES))],
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:5000',
            'due_at' => 'nullable|date',
            'max_score' => 'required|numeric|min:1|max:100',
            'period_id' => ['nullable', Rule::exists('periods', 'id')->where('school_year_id', $class->group->school_year_id)],
            'evaluation_criterion_id' => ['nullable', Rule::exists('evaluation_criteria', 'id')->where('group_subject_id', $class->id)],
            'is_published' => 'boolean',
            'files' => 'nullable|array|max:' . Assignment::MAX_FILES,
            'files.*' => 'file|max:15360',
            'remove_files' => 'nullable|array',
            'remove_files.*' => 'integer',
            'topic_id' => ['nullable', Rule::exists('class_topics', 'id')->where('group_subject_id', $class->id)->whereNull('deleted_at')],
            'links' => 'nullable|array|max:10',
            'links.*.url' => 'required|url|max:500',
            'links.*.title' => 'nullable|string|max:150',
            'rubric' => 'nullable|array|max:10',
            'rubric.*.name' => 'required|string|max:120',
            'rubric.*.description' => 'nullable|string|max:500',
            'rubric.*.levels' => 'required|array|min:2|max:6',
            'rubric.*.levels.*.label' => 'required|string|max:60',
            'rubric.*.levels.*.points' => 'required|numeric|min:0|max:100',
            'rubric.*.levels.*.description' => 'nullable|string|max:300',
        ], ['rubric.*.name.required' => 'Cada criterio de la rúbrica necesita nombre.', 'rubric.*.levels.min' => 'Cada criterio necesita al menos 2 niveles.',
            'rubric.*.levels.*.label.required' => 'Cada nivel necesita nombre.', 'rubric.*.levels.*.points.required' => 'Cada nivel necesita puntos.', 'files.max' => 'Puedes adjuntar máximo ' . Assignment::MAX_FILES . ' archivos.', 'files.*.max' => 'Cada archivo puede pesar máximo 15 MB.']);
        if (! empty($data['evaluation_criterion_id'])) {
            $data['period_id'] = EvaluationCriterion::find($data['evaluation_criterion_id'])->period_id;
        }
        $data['is_published'] = $request->boolean('is_published', true);
        unset($data['files'], $data['remove_files']);
        $data['links'] = array_values($data['links'] ?? []) ?: null;
        // La rúbrica define el puntaje máximo (suma del mejor nivel de cada criterio)
        $data['rubric'] = ! empty($data['rubric']) ? ClassroomFeed::rubric($data['rubric']) : null;
        if ($data['rubric']) {
            $data['max_score'] = max(1, ClassroomFeed::rubricMax($data['rubric']));
        }
        return $data;
    }

    /** Hasta 5 archivos por actividad: quita los marcados y agrega los nuevos. */
    private function attach(Request $request, Assignment $a, GroupSubject $class): void
    {
        $remove = (array) $request->input('remove_files', []);
        $uploads = (array) $request->file('files', []);
        if ($a->countAfter($remove, count($uploads)) > Assignment::MAX_FILES) {
            throw \Illuminate\Validation\ValidationException::withMessages(['files' => 'Puedes adjuntar máximo ' . Assignment::MAX_FILES . ' archivos por actividad.']);
        }
        $a->syncFiles($remove, $uploads, "instituciones/{$class->institution_id}/classroom/{$class->id}");
    }
}
