<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\ClassTopic;
use App\Models\GroupSubject;
use App\Support\Access;
use Illuminate\Http\Request;

/** Temas del Classroom: crear, renombrar, ordenar y borrar (las tareas pasan a "Sin tema"). */
class TopicController extends Controller
{
    public function store(Request $request, GroupSubject $class)
    {
        abort_unless(Access::canManageClass($request->user(), $class), 403);
        $data = $request->validate(['name' => 'required|string|max:120'], [], ['name' => 'nombre del tema']);
        $topic = ClassTopic::create([
            'institution_id' => $class->institution_id, 'group_subject_id' => $class->id, 'name' => trim($data['name']),
            'position' => (int) ClassTopic::where('group_subject_id', $class->id)->max('position') + 1,
        ]);

        return $request->wantsJson()
            ? response()->json(['value' => $topic->id, 'label' => $topic->name])
            : back()->with('success', 'Tema creado.');
    }

    public function update(Request $request, ClassTopic $topic)
    {
        abort_unless(Access::canManageClass($request->user(), $topic->groupSubject), 403);
        $data = $request->validate(['name' => 'required|string|max:120', 'move' => 'nullable|in:up,down'], [], ['name' => 'nombre del tema']);
        $topic->update(['name' => trim($data['name'])]);

        if (! empty($data['move'])) {
            $list = ClassTopic::where('group_subject_id', $topic->group_subject_id)->orderBy('position')->orderBy('id')->get()->values();
            $i = $list->search(fn ($t) => $t->id === $topic->id);
            $j = $data['move'] === 'up' ? $i - 1 : $i + 1;
            if ($j >= 0 && $j < $list->count()) {
                [$list[$i], $list[$j]] = [$list[$j], $list[$i]];
                $list->each(fn ($t, $k) => $t->position !== $k + 1 && $t->update(['position' => $k + 1]));
            }
        }

        return back()->with('success', 'Tema actualizado.');
    }

    public function destroy(Request $request, ClassTopic $topic)
    {
        abort_unless(Access::canManageClass($request->user(), $topic->groupSubject), 403);
        Assignment::where('topic_id', $topic->id)->update(['topic_id' => null]);
        $topic->delete(); // eliminación lógica

        return back()->with('success', 'Tema borrado. Sus tareas quedaron en "Sin tema".');
    }
}
