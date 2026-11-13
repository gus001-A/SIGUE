<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Catalogs;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Pantalla genérica para catálogos configurables. */
class CatalogController extends Controller
{
    public function index(Request $request, string $catalog)
    {
        $def = Catalogs::get($catalog);
        $model = $def['model'];
        $search = trim((string) $request->query('search'));
        $filterKey = $def['filter'] ?? null;
        $filterVal = $filterKey ? $request->query($filterKey) : null;

        $rows = $model::query()
            ->when($search, fn ($q) => $q->where(fn ($w) => collect($def['search'])->each(fn ($c) => $w->orWhere($c, 'like', "%{$search}%"))))
            ->when($filterVal, fn ($q) => $q->where($filterKey, $filterVal))
            ->orderBy(...$def['order'])
            ->get();

        $fields = collect($def['fields'])->map(fn ($f) => [
            'name' => $f[0], 'label' => $f[1], 'type' => $f[2],
            'required' => str_contains($f[3], 'required'),
            'options' => isset($f[4]['options']) ? collect($f[4]['options'])->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values() : null,
            'source' => $f[4]['source'] ?? null,
            'default' => $f[4]['default'] ?? null,
            'placeholder' => $f[4]['placeholder'] ?? null,
            'digits' => $f[4]['digits'] ?? null,
        ])->values();

        return Inertia::render('Admin/Catalogo', [
            'catalog' => [
                'key' => $catalog, 'title' => $def['title'], 'singular' => $def['singular'], 'subtitle' => $def['subtitle'],
                'icon' => $def['icon'], 'columns' => $def['columns'], 'filter' => $filterKey,
            ],
            'fields' => $fields,
            'rows' => $rows->map(fn ($r) => collect($r->getAttributes())->map(fn ($v, $k) => $r->{$k} instanceof \Carbon\Carbon ? $r->{$k}->toDateString() : $r->{$k}))->values(),
            'sources' => Catalogs::sources(),
            'filters' => ['search' => $search, 'value' => $filterVal],
            'catalogs' => collect(Catalogs::all())->map(fn ($c, $k) => ['key' => $k, 'title' => $c['title']])->values(),
        ]);
    }

    public function store(Request $request, string $catalog)
    {
        $def = Catalogs::get($catalog);
        $m = $def['model']::create($this->validated($request, $def));
        ($def['after'] ?? fn () => null)($m);

        return back()->with('success', ucfirst($def['singular']) . ' registrado.');
    }

    public function update(Request $request, string $catalog, int $id)
    {
        $def = Catalogs::get($catalog);
        $m = $def['model']::findOrFail($id);
        $m->update($this->validated($request, $def));
        ($def['after'] ?? fn () => null)($m);

        return back()->with('success', ucfirst($def['singular']) . ' actualizado.');
    }

    public function destroy(string $catalog, int $id)
    {
        $def = Catalogs::get($catalog);
        $m = $def['model']::findOrFail($id);
        if (isset($def['guard']) && ($why = $def['guard']($m))) {
            return back()->with('error', 'No se puede eliminar: ' . $why);
        }
        $m->delete();

        return back()->with('success', ucfirst($def['singular']) . ' eliminado.');
    }

    private function validated(Request $request, array $def): array
    {
        $rules = [];
        foreach ($def['fields'] as $f) {
            // Las referencias a otros catálogos se limitan a la institución activa.
            $rules[$f[0]] = preg_replace('/exists:(\w+),id/', 'exists:$1,id,institution_id,' . Tenant::id(), $f[3]);
        }
        $data = $request->validate($rules);
        foreach ($def['fields'] as $f) {
            if ($f[2] === 'switch') {
                $data[$f[0]] = $request->boolean($f[0]);
            }
        }
        return $data;
    }
}
