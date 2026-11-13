<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Controller;
use App\Models\EvaluationCriterion;
use App\Models\GradeRecord;
use App\Models\GroupSubject;
use App\Models\Period;
use App\Models\Student;
use App\Services\GradeCalculator;
use App\Services\Notifier;
use App\Support\Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Evaluaciones (DOC-EVAL-001): modelo ponderado, captura, promedios y publicación. */
class EvaluationController extends Controller
{
    private function guard(Request $request, GroupSubject $class, Period $period): void
    {
        abort_unless(Access::canManageClass($request->user(), $class), 403);
        abort_unless($period->school_year_id === $class->group->school_year_id, 404);
        if ($period->is_closed && ! $request->user()->hasPermission('academico.gestionar')) {
            abort(422, 'El periodo está cerrado; solo el personal académico puede modificarlo.');
        }
    }

    /** Guarda el modelo de evaluación del periodo. La suma de ponderaciones debe ser 100 %. */
    public function saveCriteria(Request $request, GroupSubject $class, Period $period)
    {
        $this->guard($request, $class, $period);
        $data = $request->validate([
            'criteria' => 'required|array|min:1',
            'criteria.*.id' => 'nullable|integer',
            'criteria.*.name' => 'required|string|max:80',
            'criteria.*.type' => ['required', Rule::in(array_keys(EvaluationCriterion::TYPES))],
            'criteria.*.source' => ['required', Rule::in(array_keys(EvaluationCriterion::SOURCES))],
            'criteria.*.weight' => 'required|numeric|min:0.01|max:100',
        ]);

        $sum = round(collect($data['criteria'])->sum('weight'), 2);
        if (abs($sum - 100) > 0.001) {
            throw ValidationException::withMessages(['criteria' => "La suma de ponderaciones debe ser 100 % (actual: {$sum} %)."]);
        }

        DB::transaction(function () use ($data, $class, $period) {
            $keep = [];
            foreach ($data['criteria'] as $c) {
                $row = ! empty($c['id'])
                    ? EvaluationCriterion::where('group_subject_id', $class->id)->where('period_id', $period->id)->findOrFail($c['id'])
                    : new EvaluationCriterion(['group_subject_id' => $class->id, 'period_id' => $period->id]);
                $row->fill(collect($c)->only(['name', 'type', 'source', 'weight'])->all())->save();
                $keep[] = $row->id;
            }
            EvaluationCriterion::where('group_subject_id', $class->id)->where('period_id', $period->id)->whereNotIn('id', $keep)->delete();
        });

        return back()->with('success', 'Modelo de evaluación guardado.');
    }

    /** Captura de calificaciones de criterios manuales. */
    public function saveGrades(Request $request, GroupSubject $class, Period $period)
    {
        $this->guard($request, $class, $period);
        $data = $request->validate([
            'grades' => 'required|array',
            'grades.*.student_id' => 'required|integer',
            'grades.*.criterion_id' => 'required|integer',
            'grades.*.score' => 'nullable|numeric|min:0|max:10',
        ]);

        $manual = EvaluationCriterion::where('group_subject_id', $class->id)->where('period_id', $period->id)
            ->where('source', 'manual')->pluck('id')->all();
        $enrolled = $class->group->students()->pluck('students.id')->all();

        DB::transaction(function () use ($data, $manual, $enrolled, $class, $period, $request) {
            foreach ($data['grades'] as $g) {
                if (! in_array((int) $g['criterion_id'], $manual, true) || ! in_array((int) $g['student_id'], $enrolled, true)) {
                    continue;
                }
                $rec = GradeRecord::firstOrNew([
                    'group_subject_id' => $class->id, 'period_id' => $period->id,
                    'student_id' => $g['student_id'], 'evaluation_criterion_id' => $g['criterion_id'],
                ]);
                $score = $g['score'] === null || $g['score'] === '' ? null : round((float) $g['score'], 2);
                if ($rec->exists && $rec->score === $score) {
                    continue;
                }
                $rec->fill(['score' => $score, 'updated_by' => $request->user()->id])->save();
            }
        });

        return back()->with('success', 'Calificaciones guardadas.');
    }

    /**
     * Publica el promedio del periodo: guarda la calificación final (criterio
     * null) con control de cambios y notifica a alumnos y tutores.
     */
    public function publish(Request $request, GroupSubject $class, Period $period)
    {
        $this->guard($request, $class, $period);
        $calc = new GradeCalculator($class, $period);
        if (abs($calc->totalWeight() - 100) > 0.001) {
            return back()->with('error', 'Antes de publicar, el modelo de evaluación debe sumar 100 %.');
        }

        $students = $class->group->students()->get();
        $matrix = $calc->matrix($students->pluck('id')->all());
        $published = 0;

        DB::transaction(function () use ($matrix, $class, $period, $request, &$published) {
            foreach ($matrix as $sid => $row) {
                if ($row['computed'] === null) {
                    continue;
                }
                $rec = GradeRecord::firstOrNew(['group_subject_id' => $class->id, 'period_id' => $period->id,
                    'student_id' => $sid, 'evaluation_criterion_id' => null]);
                $rec->fill(['score' => $row['computed'], 'is_published' => true, 'published_at' => now(), 'updated_by' => $request->user()->id])->save();
                $published++;
            }
        });

        if (SettingsController::notifies('calificacion')) {
            foreach ($students as $s) {
                if (($matrix[$s->id]['computed'] ?? null) === null) {
                    continue;
                }
                Notifier::student($s, 'calificacion', 'Calificación publicada: ' . $class->subject->name,
                    $period->name . ' · promedio ' . number_format($matrix[$s->id]['computed'], 1), route('portal.calificaciones'));
            }
        }

        return back()->with('success', "Se publicaron {$published} calificaciones de {$period->name}.");
    }
}
