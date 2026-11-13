<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Controller;
use App\Models\GroupSubject;
use App\Models\Incident;
use App\Models\Student;
use App\Models\TutoringSession;
use App\Services\Notifier;
use App\Support\Access;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Incidencias (DOC-INC-001) y tutorías (DOC-TUT-001). */
class FollowUpController extends Controller
{
    public function storeIncident(Request $request, GroupSubject $class)
    {
        $user = $request->user();
        abort_unless(Access::canManageClass($user, $class), 403);

        $data = $request->validate([
            'student_id' => ['required', Rule::in($class->group->students()->pluck('students.id')->all())],
            'date' => 'required|date|before_or_equal:today',
            'category' => ['required', Rule::in(array_keys(Incident::CATEGORIES))],
            'description' => 'required|string|max:3000',
            'priority' => ['required', Rule::in(array_keys(Incident::PRIORITIES))],
            'notify_guardian' => 'boolean',
            'evidence' => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png',
        ]);

        $incident = Incident::create([
            ...collect($data)->except('evidence')->all(),
            'group_subject_id' => $class->id,
            'teacher_id' => $user->teacher?->id,
            'reported_by' => $user->id,
            'evidence_path' => $request->hasFile('evidence')
                ? $request->file('evidence')->store("instituciones/{$class->institution_id}/incidencias", 'local') : null,
        ]);

        // Las incidencias no modifican calificaciones (regla de DOC-INC-001).
        if ($incident->notify_guardian && SettingsController::notifies('incidencia')) {
            Notifier::student($incident->student, 'incidencia', (Incident::CATEGORIES[$incident->category] ?? 'Incidencia') . ' · ' . $class->subject->name,
                $incident->description, route('portal.incidencias'), $incident, includeStudent: false);
        }

        return back()->with('success', 'Incidencia registrada.');
    }

    public function tutorias(Request $request)
    {
        $teacher = $request->user()->teacher;
        $groupIds = GroupSubject::where('teacher_id', $teacher?->id)->pluck('group_id')
            ->merge(\App\Models\Group::where('tutor_teacher_id', $teacher?->id)->pluck('id'))->unique();

        $students = Student::whereHas('enrollments', fn ($q) => $q->whereIn('group_id', $groupIds)->where('status', 'autorizada'))
            ->orderBy('paternal_surname')->get();

        $sessions = TutoringSession::with('student:id,name,paternal_surname,maternal_surname')
            ->where('teacher_id', $teacher?->id)->latest('date')->get();

        return Inertia::render('Docente/Tutorias', [
            'sessions' => $sessions->map(fn ($t) => [
                ...$t->only(['id', 'student_id', 'topic', 'observations', 'agreements', 'results', 'status']),
                'date' => $t->date?->toDateString(), 'follow_up_on' => $t->follow_up_on?->toDateString(),
                'student' => $t->student?->full_name, 'alert' => $t->status === 'abierta' && $t->follow_up_on?->isPast(),
                'date_label' => $t->date?->format('d/m/Y'), 'follow_label' => $t->follow_up_on?->format('d/m/Y'),
            ]),
            'students' => $students->map(fn ($s) => ['value' => $s->id, 'label' => $s->listName()]),
            'hasTeacher' => (bool) $teacher,
        ]);
    }

    public function storeTutoria(Request $request)
    {
        $teacher = $request->user()->teacher;
        abort_unless($teacher, 403, 'Tu usuario no está vinculado a un docente.');
        $data = $this->tutoriaData($request);
        abort_unless(Access::teachesStudent($request->user(), (int) $data['student_id'])
            || \App\Models\Group::where('tutor_teacher_id', $teacher->id)->whereHas('enrollments', fn ($q) => $q->where('student_id', $data['student_id']))->exists(), 403);

        TutoringSession::create($data + ['teacher_id' => $teacher->id]);

        return back()->with('success', 'Sesión de tutoría registrada.');
    }

    public function updateTutoria(Request $request, TutoringSession $session)
    {
        abort_unless($session->teacher_id === $request->user()->teacher?->id, 403);
        $session->update($this->tutoriaData($request));

        return back()->with('success', 'Tutoría actualizada.');
    }

    private function tutoriaData(Request $request): array
    {
        return $request->validate([
            'student_id' => 'required|integer|exists:students,id',
            'date' => 'required|date',
            'topic' => 'required|string|max:200',
            'observations' => 'nullable|string|max:5000',
            'agreements' => 'nullable|string|max:5000',
            'results' => 'nullable|string|max:5000',
            'follow_up_on' => 'nullable|date',
            'status' => 'required|in:abierta,cerrada',
        ]);
    }
}
