<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Support\Options;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Vista institucional de incidencias y su seguimiento. */
class IncidentController extends Controller
{
    public function index(Request $request)
    {
        $f = $request->only(['category', 'status', 'priority', 'group_id', 'search', 'from', 'to']);
        // Rango de fechas (de tal a tal fecha); se acepta en cualquier orden.
        $from = rescue(fn () => ! empty($f['from']) ? \Carbon\Carbon::parse($f['from'])->toDateString() : null, null, false);
        $to = rescue(fn () => ! empty($f['to']) ? \Carbon\Carbon::parse($f['to'])->toDateString() : null, null, false);
        if ($from && $to && $from > $to) {
            [$from, $to] = [$to, $from];
        }
        $f['from'] = $from;
        $f['to'] = $to;

        $items = Incident::with(['student:id,name,paternal_surname,maternal_surname', 'teacher:id,name,paternal_surname,maternal_surname', 'groupSubject.subject:id,name', 'reporter:id,name,paternal_surname'])
            ->when($from, fn ($q, $d) => $q->whereDate('date', '>=', $d))
            ->when($to, fn ($q, $d) => $q->whereDate('date', '<=', $d))
            ->when($f['category'] ?? null, fn ($q, $v) => $q->where('category', $v))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($f['priority'] ?? null, fn ($q, $v) => $q->where('priority', $v))
            ->when($f['group_id'] ?? null, fn ($q, $g) => $q->whereHas('student.enrollments', fn ($e) => $e->where('group_id', $g)->where('status', 'autorizada')))
            ->when($f['search'] ?? null, fn ($q, $s) => $q->whereHas('student', fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('paternal_surname', 'like', "%{$s}%")))
            ->latest('date')->latest('id')->get()
            ->map(fn ($i) => [
                'id' => $i->id, 'date' => $i->date?->format('d/m/Y'), 'date_iso' => $i->date?->toDateString(), 'student' => $i->student?->full_name, 'student_id' => $i->student_id,
                'created_by' => $i->reporter ? trim($i->reporter->name . ' ' . $i->reporter->paternal_surname) : ($i->teacher?->full_name ?? 'Sistema'),
                'created_at' => $i->created_at?->format('d/m/Y H:i'),
                'teacher' => $i->teacher?->full_name, 'subject' => $i->groupSubject?->subject?->name,
                'category' => $i->category, 'category_label' => Incident::CATEGORIES[$i->category] ?? $i->category,
                'positive' => $i->isPositive(), 'description' => $i->description, 'priority' => $i->priority,
                'status' => $i->status, 'follow_up' => $i->follow_up, 'has_evidence' => (bool) $i->evidence_path, 'evidence_url' => $i->evidenceUrl(), 'evidence_name' => $i->evidenceName(),
            ]);

        return Inertia::render('Admin/Incidencias/Index', [
            'items' => $items,
            'filters' => $f,
            'options' => [
                'categories' => Options::from(Incident::CATEGORIES),
                'statuses' => Options::from(Incident::STATUSES),
                'priorities' => Options::from(Incident::PRIORITIES),
                'groups' => Options::groups(),
            ],
            'stats' => [
                'abiertas' => Incident::where('status', 'abierta')->count(),
                'seguimiento' => Incident::where('status', 'seguimiento')->count(),
                'alta' => Incident::where('priority', 'alta')->where('status', '!=', 'cerrada')->count(),
                'positivas' => Incident::whereIn('category', ['conducta_positiva', 'participacion'])->where('date', '>=', now()->subDays(30))->count(),
            ],
        ]);
    }

    public function update(Request $request, Incident $incident)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Incident::STATUSES))],
            'follow_up' => 'nullable|string|max:3000',
        ]);
        $incident->update($data);

        return back()->with('success', 'Seguimiento actualizado.');
    }
}
