<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Catalogs;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/** Configuración institucional: datos, logotipo, color y preferencias de notificación. */
class SettingsController extends Controller
{
    public const NOTIFY = [
        'falta' => 'Faltas', 'retardo' => 'Retardos', 'tarea' => 'Nuevas tareas', 'calificacion' => 'Calificaciones publicadas',
        'incidencia' => 'Incidencias', 'adeudo' => 'Adeudos', 'documento' => 'Revisión de documentos',
    ];

    public function edit()
    {
        $i = Tenant::institution();
        $settings = $i->settings ?? [];

        return Inertia::render('Admin/Configuracion', [
            'institution' => [
                ...$i->only(['name', 'short_name', 'cct', 'rfc', 'email', 'phone', 'address', 'postal_code', 'colony', 'municipality', 'state', 'primary_color', 'plan']),
                'logo_url' => $i->logoUrl(),
                'license_expires_at' => $i->license_expires_at?->format('d/m/Y'),
                'max_students' => $i->max_students,
            ],
            'settings' => [
                'notify' => $settings['notify'] ?? array_keys(self::NOTIFY),
                'passing_score' => $settings['passing_score'] ?? 6,
                'grade_scale' => $settings['grade_scale'] ?? 10,
            ],
            'campus' => \App\Support\Tenant::campus() ? [
                'id' => \App\Support\Tenant::campus()->id, 'name' => \App\Support\Tenant::campus()->name,
                'breaks' => collect(\App\Models\Campus::SHIFTS)->map(fn ($label, $k) => [
                    'shift' => $k, 'label' => $label,
                    'start' => \App\Support\Tenant::campus()->breaks[$k]['start'] ?? null,
                    'end' => \App\Support\Tenant::campus()->breaks[$k]['end'] ?? null,
                ])->values(),
            ] : null,
            'notifyOptions' => collect(self::NOTIFY)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values(),
            'catalogs' => collect(Catalogs::all())->map(fn ($c, $k) => ['key' => $k, 'title' => $c['title'], 'subtitle' => $c['subtitle'], 'icon' => $c['icon']])->values(),
        ]);
    }

    public function update(Request $request)
    {
        $i = Tenant::institution();
        $data = $request->validate([
            'name' => 'required|string|max:180',
            'short_name' => 'nullable|string|max:60',
            'cct' => 'nullable|string|max:30',
            'rfc' => ['nullable', 'string', 'regex:/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/iu'],
            'email' => 'nullable|email',
            'phone' => ['nullable', 'digits:10'],
            'address' => 'nullable|string|max:255',
            'postal_code' => ['nullable', 'digits:5'],
            'colony' => 'nullable|string|max:120',
            'municipality' => 'nullable|string|max:120',
            'state' => 'nullable|string|max:60',
            'primary_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo' => 'nullable|image|max:2048',
            'notify' => 'array',
            'notify.*' => 'in:' . implode(',', array_keys(self::NOTIFY)),
            'passing_score' => 'required|numeric|min:0|max:10',
        ]);

        if ($request->hasFile('logo')) {
            if ($i->logo_path) {
                Storage::disk('local')->delete($i->logo_path);
            }
            $i->logo_path = $request->file('logo')->store("instituciones/{$i->id}/logo", 'local');
        }

        $i->fill(collect($data)->except(['logo', 'notify', 'passing_score'])->all());
        $i->settings = array_merge($i->settings ?? [], [
            'notify' => $data['notify'] ?? [],
            'passing_score' => (float) $data['passing_score'],
        ]);
        $i->save();
        \App\Models\AuditLog::record('updated', $i, 'Configuración institucional');

        return back()->with('success', 'Configuración guardada.');
    }

    /** ¿La institución tiene habilitado este tipo de notificación? */
    public static function notifies(string $type): bool
    {
        $s = Tenant::institution()?->settings ?? [];
        return in_array($type, $s['notify'] ?? array_keys(self::NOTIFY), true);
    }

    /** Receso por turno del plantel activo; aplica a todos los grados del plantel. */
    public function saveBreaks(Request $request)
    {
        $campus = \App\Support\Tenant::campus();
        abort_unless($campus, 422, 'Elige un plantel para configurar su receso.');
        $data = $request->validate([
            'breaks' => 'array',
            'breaks.*.shift' => 'required|in:' . implode(',', array_keys(\App\Models\Campus::SHIFTS)),
            'breaks.*.start' => 'nullable|date_format:H:i',
            'breaks.*.end' => 'nullable|date_format:H:i',
        ]);
        $out = [];
        foreach ($data['breaks'] ?? [] as $i => $b) {
            if (empty($b['start']) && empty($b['end'])) continue;
            if (empty($b['start']) || empty($b['end']) || $b['end'] <= $b['start']) {
                return back()->withErrors(["breaks.$i.end" => 'La hora de fin debe ser posterior al inicio.']);
            }
            $out[$b['shift']] = ['start' => $b['start'], 'end' => $b['end']];
        }
        \App\Models\Campus::whereKey($campus->id)->update(['breaks' => $out ? json_encode($out) : null]);

        return back()->with('success', 'Receso del plantel ' . $campus->name . ' guardado.');
    }
}
