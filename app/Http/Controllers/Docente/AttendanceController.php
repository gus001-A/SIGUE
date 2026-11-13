<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\GroupSubject;
use App\Models\Student;
use App\Services\Notifier;
use App\Support\Access;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Pase de lista (DOC-ASIS-001). */
class AttendanceController extends Controller
{
    /** Días que tiene el docente para capturar o corregir un pase de lista. */
    public const EDIT_DAYS = 7;

    /** Hora de inicio (HH:MM) de la sesión n de la clase en esa fecha, según el horario. */
    public static function sessionStart(GroupSubject $class, \Carbon\Carbon $date, int $session): ?string
    {
        $t = $class->schedules()->where('day_of_week', $date->dayOfWeekIso)->orderBy('starts_at')->pluck('starts_at')->values()->get($session - 1);

        return $t ? substr($t, 0, 5) : null;
    }

    public function store(Request $request, GroupSubject $class)
    {
        $user = $request->user();
        abort_unless(Access::canManageClass($user, $class), 403);

        $data = $request->validate([
            'date' => 'required|date|before_or_equal:today',
            'session' => 'required|integer|min:1|max:10',
            'rows' => 'required|array|min:1',
            'rows.*.student_id' => 'required|integer',
            'rows.*.status' => ['required', Rule::in(array_keys(Attendance::STATUSES))],
            'rows.*.notes' => 'nullable|string|max:255',
        ]);

        // El día debe ser día de clase según el horario de la materia (o día hábil si aún no tiene horario).
        $date = \Carbon\Carbon::parse($data['date']);
        $days = $class->schedules()->pluck('day_of_week')->map(fn ($d) => (int) $d)->unique();
        $classDays = $days->isNotEmpty() ? $days->all() : [1, 2, 3, 4, 5];
        if (! in_array($date->dayOfWeekIso, $classDays, true)) {
            $names = collect($classDays)->sort()->map(fn ($d) => \App\Models\Schedule::DAYS[$d])->implode(', ');
            return back()->with('error', 'El ' . $date->isoFormat('dddd D [de] MMMM') . " no hay clase. Días de clase: {$names}.");
        }
        $sessions = max(1, $class->schedules()->where('day_of_week', $date->dayOfWeekIso)->count());
        if ($data['session'] > $sessions) {
            return back()->with('error', "Ese día la clase solo tiene {$sessions} sesión(es).");
        }
        // Plazo: la lista se puede capturar o corregir hasta 7 días después de la clase.
        if ($date->lt(today()->subDays(self::EDIT_DAYS))) {
            return back()->with('error', 'El plazo para modificar este pase de lista terminó (máximo ' . self::EDIT_DAYS . ' días después de la clase).');
        }
        // Hoy solo se puede pasar lista a partir de la hora de inicio de la sesión.
        $start = self::sessionStart($class, $date, (int) $data['session']);
        if ($date->isToday() && $start && now()->format('H:i') < $start) {
            return back()->with('error', "La clase empieza a las {$start}. Podrás pasar lista a partir de esa hora.");
        }
        // Si el alumno está presente no se guarda observación.
        $data['rows'] = collect($data['rows'])->map(fn ($r) => $r['status'] === 'presente' ? [...$r, 'notes' => null] : $r)->all();

        $enrolled = $class->group->students()->pluck('students.id')->all();
        $existing = Attendance::where('group_subject_id', $class->id)->whereDate('date', $data['date'])
            ->where('session', $data['session'])->get()->keyBy('student_id');
        // Editar la lista de un día ya capturado está permitido; cada cambio queda registrado internamente.

        $notify = [];
        DB::transaction(function () use ($data, $class, $enrolled, $existing, $user, &$notify) {
            foreach ($data['rows'] as $row) {
                if (! in_array((int) $row['student_id'], $enrolled, true)) {
                    continue;
                }
                $prev = $existing->get($row['student_id']);
                $record = Attendance::updateOrCreate(
                    ['group_subject_id' => $class->id, 'student_id' => $row['student_id'], 'date' => $data['date'], 'session' => $data['session']],
                    ['status' => $row['status'], 'notes' => $row['notes'] ?? null, 'recorded_by' => $user->id]
                );
                if (in_array($row['status'], ['falta', 'retardo'], true) && (! $prev || $prev->status !== $row['status'])) {
                    $notify[] = $record;
                }
            }
        });

        foreach ($notify as $r) {
            if (! SettingsController::notifies($r->status)) {
                continue;
            }
            $student = Student::find($r->student_id);
            Notifier::student($student, $r->status, ($r->status === 'falta' ? 'Falta' : 'Retardo') . ' en ' . $class->subject->name,
                $student->full_name . ' · ' . $r->date->format('d/m/Y'), route('portal.asistencias'), $r, includeStudent: false);
        }

        return back()->with('success', $existing->isNotEmpty()
            ? 'Lista del ' . $date->isoFormat('D [de] MMMM') . ' actualizada.'
            : 'Pase de lista guardado.');
    }

    /** Reporte de asistencia de la clase (PDF). */
    public function report(Request $request, GroupSubject $class)
    {
        abort_unless(Access::canManageClass($request->user(), $class), 403);
        $class->load(['group.grade', 'group.institution', 'subject', 'teacher']);
        $from = $request->query('from', today()->subMonth()->toDateString());
        $to = $request->query('to', today()->toDateString());

        $students = $class->group->students()->get();
        $rows = Attendance::where('group_subject_id', $class->id)->whereBetween('date', [$from, $to])->get()->groupBy('student_id');
        $dates = Attendance::where('group_subject_id', $class->id)->whereBetween('date', [$from, $to])->distinct()->orderBy('date')->pluck('date');

        return Pdf::loadView('pdf.asistencia', compact('class', 'students', 'rows', 'dates', 'from', 'to'))
            ->setPaper('letter', 'landscape')->download('asistencia.pdf');
    }
}
