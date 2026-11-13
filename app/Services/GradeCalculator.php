<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\EvaluationCriterion;
use App\Models\GradeRecord;
use App\Models\GroupSubject;
use App\Models\Period;
use App\Models\Submission;
use Illuminate\Support\Collection;

/**
 * Cálculo de calificaciones (DOC-EVAL-001): cada criterio aporta una nota
 * en escala 0–10 y el promedio del periodo es la suma ponderada.
 *
 *  - manual:     nota capturada por el docente (grades_records).
 *  - tareas:     promedio de las entregas de las tareas ligadas al criterio.
 *  - asistencia: % de asistencia del periodo (retardo cuenta 0.5) / 10.
 */
class GradeCalculator
{
    public function __construct(private GroupSubject $class, private Period $period) {}

    /** @return Collection<EvaluationCriterion> */
    public function criteria(): Collection
    {
        return EvaluationCriterion::where('group_subject_id', $this->class->id)
            ->where('period_id', $this->period->id)->orderBy('id')->get();
    }

    public function totalWeight(): float
    {
        return round((float) $this->criteria()->sum('weight'), 2);
    }

    /**
     * Matriz [student_id => ['criteria' => [criterion_id => score|null], 'final' => float|null]]
     */
    public function matrix(array $studentIds): array
    {
        $criteria = $this->criteria();
        $manual = GradeRecord::where('group_subject_id', $this->class->id)
            ->where('period_id', $this->period->id)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->groupBy('student_id');

        $out = [];
        foreach ($studentIds as $sid) {
            $rows = $manual->get($sid, collect());
            $scores = [];
            foreach ($criteria as $c) {
                $scores[$c->id] = match ($c->source) {
                    'tareas' => $this->assignmentsScore($c, $sid),
                    'asistencia' => $this->attendanceScore($sid),
                    default => optional($rows->firstWhere('evaluation_criterion_id', $c->id))->score,
                };
            }
            $final = $rows->firstWhere('evaluation_criterion_id', null);
            $out[$sid] = [
                'criteria' => $scores,
                'computed' => $this->weighted($criteria, $scores),
                'published' => $final?->is_published ? $final->score : null,
                'is_published' => (bool) $final?->is_published,
            ];
        }
        return $out;
    }

    private function weighted(Collection $criteria, array $scores): ?float
    {
        if ($criteria->isEmpty()) {
            return null;
        }
        $sum = 0; $weights = 0; $any = false;
        foreach ($criteria as $c) {
            $s = $scores[$c->id] ?? null;
            if ($s !== null) {
                $any = true;
            }
            $sum += ((float) ($s ?? 0)) * $c->weight;
            $weights += $c->weight;
        }
        if (! $any || $weights <= 0) {
            return null;
        }
        // Si el modelo no suma 100 % se normaliza para no castigar al alumno.
        return round($sum / $weights, 2);
    }

    private function assignmentsScore(EvaluationCriterion $c, int $studentId): ?float
    {
        $subs = Submission::whereHas('assignment', fn ($q) => $q->where('evaluation_criterion_id', $c->id))
            ->where('student_id', $studentId)->whereNotNull('score')
            ->with('assignment:id,max_score')->get();

        $total = \App\Models\Assignment::where('evaluation_criterion_id', $c->id)->where('is_published', true)->count();
        if ($total === 0) {
            return null;
        }
        $sum = $subs->sum(fn ($s) => $s->assignment->max_score > 0 ? ($s->score / $s->assignment->max_score) * 10 : 0);
        // Las tareas no entregadas cuentan como 0.
        return round($sum / $total, 2);
    }

    private function attendanceScore(int $studentId): ?float
    {
        $q = Attendance::where('group_subject_id', $this->class->id)->where('student_id', $studentId);
        if ($this->period->starts_on) {
            $q->whereDate('date', '>=', $this->period->starts_on);
        }
        if ($this->period->ends_on) {
            $q->whereDate('date', '<=', $this->period->ends_on);
        }
        $rows = $q->pluck('status');
        if ($rows->isEmpty()) {
            return null;
        }
        $points = $rows->sum(fn ($s) => match ($s) {
            'presente', 'justificada' => 1,
            'retardo' => 0.5,
            default => 0,
        });
        return round($points / $rows->count() * 10, 2);
    }
}
