<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Student;
use App\Services\Notifier;
use App\Support\Access;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Gestión documental: carga, clasificación, validación y expedientes incompletos. */
class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'pendiente');
        $tab = $request->query('tab', 'revision');

        $docs = Document::with(['student:id,name,paternal_surname,maternal_surname,enrollment_number', 'type:id,name'])
            ->latest()->get()
            ->map(fn ($d) => $d->toView() + [
                'student' => $d->student?->full_name,
                'student_id' => $d->student_id,
                'enrollment_number' => $d->student?->enrollment_number,
            ]);

        // Expedientes incompletos: alumnos activos a los que les falta algún documento obligatorio.
        $required = DocumentType::where('is_required', true)->get(['id', 'name']);
        $incomplete = [];
        if ($required->isNotEmpty()) {
            $students = Student::whereIn('status', ['aspirante', 'activo'])
                ->with(['documents' => fn ($q) => $q->whereIn('status', ['pendiente', 'validado'])])
                ->orderBy('paternal_surname')->get();
            foreach ($students as $s) {
                $have = $s->documents->pluck('document_type_id')->filter()->all();
                $missing = $required->whereNotIn('id', $have)->pluck('name')->values();
                if ($missing->isNotEmpty()) {
                    $incomplete[] = ['id' => $s->id, 'name' => $s->listName(), 'enrollment_number' => $s->enrollment_number, 'missing' => $missing];
                }
            }
        }

        return Inertia::render('Admin/Documentos/Index', [
            'documents' => $docs,
            'incomplete' => $incomplete,
            'tab' => $tab,
            'status' => $status,
            'stats' => [
                'pendiente' => Document::where('status', 'pendiente')->count(),
                'validado' => Document::where('status', 'validado')->count(),
                'rechazado' => Document::where('status', 'rechazado')->count(),
                'correccion' => Document::where('status', 'correccion')->count(),
            ],
        ]);
    }

    /** Carga de documento (personal administrativo, alumno o tutor). */
    public function store(Request $request, Student $student)
    {
        $user = $request->user();
        abort_unless($user->hasPermission('documentos.validar') || $user->hasPermission('alumnos.editar') || Access::isOwnOrChild($user, $student->id), 403);

        $data = $request->validate([
            'document_type_id' => ['nullable', Rule::exists('document_types', 'id')->where('institution_id', Tenant::id())],
            'title' => 'nullable|string|max:150',
            'file' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png',
        ]);
        $file = $request->file('file');
        $type = isset($data['document_type_id']) ? DocumentType::find($data['document_type_id']) : null;

        $doc = Document::create([
            'student_id' => $student->id,
            'document_type_id' => $type?->id,
            'title' => $data['title'] ?: ($type?->name ?? $file->getClientOriginalName()),
            'file_path' => $file->store("instituciones/{$student->institution_id}/expedientes/{$student->id}", 'local'),
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'status' => $user->hasPermission('documentos.validar') && $request->boolean('validate') ? 'validado' : 'pendiente',
            'uploaded_by' => $user->id,
        ]);

        return back()->with('success', 'Documento cargado' . ($doc->status === 'pendiente' ? '; queda en revisión.' : ' y validado.'));
    }

    public function review(Request $request, Document $document)
    {
        $data = $request->validate([
            'status' => 'required|in:validado,rechazado,correccion',
            'review_notes' => 'required_unless:status,validado|nullable|string|max:1000',
        ], ['review_notes.required_unless' => 'Indica el motivo para rechazar o solicitar corrección.']);

        $document->update($data + ['reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);

        $msg = match ($data['status']) {
            'validado' => 'fue validado',
            'rechazado' => 'fue rechazado: ' . $data['review_notes'],
            default => 'requiere corrección: ' . $data['review_notes'],
        };
        Notifier::student($document->student, 'documento', 'Documento: ' . $document->title, "El documento {$msg}", route('portal.documentos'), $document);

        return back()->with('success', 'Revisión guardada.');
    }

    public function destroy(Request $request, Document $document)
    {
        $user = $request->user();
        $own = Access::isOwnOrChild($user, $document->student_id) && $document->status !== 'validado';
        abort_unless($user->hasPermission('documentos.validar') || $own, 403);

        Storage::disk('local')->delete($document->file_path);
        $document->delete();

        return back()->with('success', 'Documento eliminado.');
    }
}
