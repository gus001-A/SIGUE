<?php

namespace App\Http\Controllers;

use App\Exports\ArrayExport;
use App\Models\Student;
use App\Support\Options;
use App\Support\Reports;
use App\Support\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

/** Centro de reportes (MOD-REP-001) con exportación PDF, Excel y CSV. */
class ReportController extends Controller
{
    /** Catálogo de reportes en tarjetas, agrupados por categoría. */
    public function index(Request $request)
    {
        $user = $request->user();

        return Inertia::render('Reportes/Index', [
            'reports' => collect(Reports::LIST)
                ->filter(fn ($r) => $user->hasPermission($r[4]))
                ->map(fn ($r, $k) => ['key' => $k, 'category' => $r[0], 'title' => $r[1], 'description' => $r[2], 'params' => $r[3]])
                ->values(),
        ]);
    }

    /** Vista del reporte: filtros, totales, tabla y exportación. */
    public function show(Request $request, string $report)
    {
        $def = Reports::LIST[$report] ?? abort(404);
        abort_unless($request->user()->hasPermission($def[4]), 403);

        $periods = Options::periods();
        $filters = array_filter([
            'group_id' => $request->integer('group_id') ?: null,
            'period_id' => $request->integer('period_id') ?: (in_array('period_id', $def[3]) ? ($periods[0]['value'] ?? null) : null),
            'student_id' => $request->integer('student_id') ?: null,
            'from' => $request->input('from') ?: (in_array('from', $def[3]) ? today()->startOfMonth()->toDateString() : null),
            'to' => $request->input('to') ?: (in_array('to', $def[3]) ? today()->toDateString() : null),
        ], fn ($v) => $v !== null);
        $filters = array_intersect_key($filters, array_flip($def[3]));

        // Parámetros obligatorios para poder construir el reporte
        $required = ['calificaciones' => ['group_id' => 'un grupo', 'period_id' => 'un periodo'], 'kardex' => ['student_id' => 'un alumno']][$report] ?? [];
        $missing = collect($required)->reject(fn ($label, $k) => ! empty($filters[$k]))->values()->all();

        return Inertia::render('Reportes/Ver', [
            'report' => ['key' => $report, 'category' => $def[0], 'title' => $def[1], 'description' => $def[2], 'params' => $def[3]],
            'filters' => (object) $filters,
            'missing' => $missing,
            'data' => $missing ? null : Reports::build($report, $filters),
            'generatedAt' => now()->toIso8601String(),
            'generatedBy' => $request->user()->name,
            'options' => [
                'groups' => in_array('group_id', $def[3]) ? Options::groups() : [],
                'periods' => in_array('period_id', $def[3]) ? $periods : [],
                'students' => in_array('student_id', $def[3])
                    ? Student::orderBy('paternal_surname')->get()->map(fn ($s) => ['value' => $s->id, 'label' => $s->listName() . ' · ' . $s->enrollment_number])
                    : [],
            ],
        ]);
    }

    public function export(Request $request, string $report, string $format)
    {
        $data = $this->data($request, $report);
        $name = Str::slug($data['title']) . '-' . now()->format('Ymd-His');

        return match ($format) {
            'xlsx' => Excel::download(new ArrayExport($data['headings'], $data['rows'], $data['title']), "{$name}.xlsx", ExcelFormat::XLSX),
            'csv' => Excel::download(new ArrayExport($data['headings'], $data['rows'], $data['title']), "{$name}.csv", ExcelFormat::CSV),
            'pdf' => Pdf::loadView('pdf.reporte', $data + ['institution' => Tenant::institution()])
                ->setPaper('letter', count($data['headings']) > 6 ? 'landscape' : 'portrait')->download("{$name}.pdf"),
            default => abort(404),
        };
    }

    private function data(Request $request, string $report): array
    {
        $def = Reports::LIST[$report] ?? abort(404);
        abort_unless($request->user()->hasPermission($def[4]), 403);

        return Reports::build($report, $request->only(['group_id', 'period_id', 'student_id', 'from', 'to']));
    }
}
