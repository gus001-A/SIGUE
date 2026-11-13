<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Controller;
use App\Models\ClassPost;
use App\Models\GroupSubject;
use App\Services\Notifier;
use App\Support\Access;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Novedades del Classroom: avisos y material de la clase (archivos y enlaces como diapositivas o videos). */
class StreamController extends Controller
{
    public function store(Request $request, GroupSubject $class)
    {
        abort_unless(Access::canManageClass($request->user(), $class), 403);
        $data = $this->validated($request);

        $post = new ClassPost($data + ['group_subject_id' => $class->id, 'institution_id' => $class->institution_id, 'user_id' => $request->user()->id]);
        $this->attach($request, $post, $class);
        $post->save();

        if (SettingsController::notifies('tarea')) {
            $label = $post->type === 'material' ? 'Nuevo material' : 'Nuevo aviso';
            foreach ($class->group->students()->get() as $s) {
                Notifier::student($s, 'tarea', "{$label} · {$class->subject->name}", $post->title ?: mb_strimwidth((string) $post->body, 0, 120, '…'),
                    route('portal.clases', ['clase' => $class->id]), $post);
            }
        }

        return back()->with('success', $post->type === 'material' ? 'Material publicado.' : 'Aviso publicado.');
    }

    public function update(Request $request, ClassPost $post)
    {
        $class = $post->groupSubject;
        abort_unless(Access::canManageClass($request->user(), $class), 403);
        $post->fill($this->validated($request));
        $this->attach($request, $post, $class);
        $post->save();

        return back()->with('success', 'Publicación actualizada.');
    }

    public function pin(Request $request, ClassPost $post)
    {
        abort_unless(Access::canManageClass($request->user(), $post->groupSubject), 403);
        $post->update(['pinned' => ! $post->pinned]);

        return back()->with('success', $post->pinned ? 'Publicación fijada arriba.' : 'Publicación desfijada.');
    }

    public function destroy(Request $request, ClassPost $post)
    {
        abort_unless(Access::canManageClass($request->user(), $post->groupSubject), 403);
        $post->delete(); // eliminación lógica

        return back()->with('success', 'Publicación eliminada.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(ClassPost::TYPES))],
            'title' => 'nullable|string|max:200|required_if:type,material',
            'body' => 'nullable|string|max:5000',
            'links' => 'nullable|array|max:10',
            'links.*.url' => 'required|url|max:500',
            'links.*.title' => 'nullable|string|max:150',
            'files' => 'nullable|array|max:' . ClassPost::MAX_FILES,
            'files.*' => 'file|max:51200',
            'remove_files' => 'nullable|array',
            'remove_files.*' => 'integer',
        ], [
            'title.required_if' => 'Ponle un título al material.',
            'links.*.url.url' => 'El enlace debe ser una dirección completa (https://…).',
            'files.max' => 'Puedes adjuntar máximo ' . ClassPost::MAX_FILES . ' archivos.',
            'files.*.max' => 'Cada archivo puede pesar máximo 50 MB.',
        ], ['title' => 'título', 'body' => 'texto']);

        $empty = trim((string) ($data['body'] ?? '')) === '' && ! $request->hasFile('files') && empty($data['links']) && empty($data['title']);
        if ($empty) {
            throw ValidationException::withMessages(['body' => 'Escribe el aviso o agrega material.']);
        }
        $data['links'] = array_values($data['links'] ?? []);
        unset($data['files'], $data['remove_files']);

        return $data;
    }

    private function attach(Request $request, ClassPost $post, GroupSubject $class): void
    {
        $remove = (array) $request->input('remove_files', []);
        $uploads = (array) $request->file('files', []);
        if ($post->countAfter($remove, count($uploads)) > ClassPost::MAX_FILES) {
            throw ValidationException::withMessages(['files' => 'Puedes adjuntar máximo ' . ClassPost::MAX_FILES . ' archivos por publicación.']);
        }
        $post->syncFiles($remove, $uploads, "instituciones/{$class->institution_id}/material/{$class->id}");
    }
}
