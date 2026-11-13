<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ArrayExport;
use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Guardian;
use App\Models\Role;
use App\Models\SchoolYear;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Importación masiva de alumnos desde Excel (solo administrador institucional o superadministrador).
 * 1) Se descarga la plantilla. 2) Se sube el archivo y se valida fila por fila (vista previa).
 * 3) Se confirman e importan las filas válidas al plantel activo.
 */
class StudentImportController extends Controller
{
    /** Columnas de la plantilla: [encabezado, clave, ejemplo]. */
    private const COLUMNS = [
        ['Nombre(s)*', 'name', 'VALENTINA'],
        ['Apellido paterno*', 'paternal_surname', 'CASTILLO'],
        ['Apellido materno', 'maternal_surname', 'JIMÉNEZ'],
        ['CURP', 'curp', 'CAJV130627MDFSMLA1'],
        ['Fecha de nacimiento (DD/MM/AAAA)', 'birth_date', '27/06/2013'],
        ['Sexo (M/H/X)', 'gender', 'M'],
        ['Correo', 'email', ''],
        ['Teléfono', 'phone', '5512345678'],
        ['Grupo (ej. 1° A)', 'group', '1° A'],
        ['Código postal', 'postal_code', '06700'],
        ['Calle y número', 'address', 'Av. Juárez 120'],
        ['Tutor: nombre completo', 'guardian_name', 'VERÓNICA CASTILLO MORALES'],
        ['Tutor: parentesco (padre/madre/tutor/abuelo/otro)', 'guardian_relationship', 'madre'],
        ['Tutor: teléfono', 'guardian_phone', '5598765432'],
        ['Tutor: correo', 'guardian_email', ''],
    ];

    private function authorizeImport(Request $request): void
    {
        $u = $request->user();
        abort_unless($u->isSuperAdmin() || $u->hasRole(Role::ADMIN), 403, 'Solo el administrador puede importar alumnos.');
    }

    public function template(Request $request)
    {
        $this->authorizeImport($request);
        return Excel::download(new ArrayExport(array_column(self::COLUMNS, 0), [array_column(self::COLUMNS, 2)], 'Alumnos'), 'plantilla-alumnos-sigue.xlsx');
    }

    /** Valida el archivo y devuelve la vista previa (no guarda nada). */
    public function preview(Request $request)
    {
        $this->authorizeImport($request);
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:10240']);
        $path = $request->file('file')->store('importaciones', 'local');
        $rows = $this->analyze(null, $path);
        session(['student_import' => $path]);

        return response()->json([
            'rows' => $rows,
            'valid' => collect($rows)->where('ok', true)->count(),
            'invalid' => collect($rows)->where('ok', false)->count(),
        ]);
    }

    /** Importa las filas válidas del último archivo revisado. */
    public function import(Request $request)
    {
        $this->authorizeImport($request);
        $path = session('student_import');
        abort_unless($path && \Illuminate\Support\Facades\Storage::disk('local')->exists($path), 422, 'Vuelve a subir el archivo.');
        $rows = collect($this->analyze(null, $path))->where('ok', true);
        $authorize = $request->boolean('authorize', true);

        $created = 0; $enrolled = 0; $guardians = 0;
        DB::transaction(function () use ($rows, $authorize, &$created, &$enrolled, &$guardians) {
            foreach ($rows as $r) {
                $d = $r['data'];
                $student = Student::create([
                    'enrollment_number' => $this->nextEnrollmentNumber(),
                    'name' => $d['name'], 'paternal_surname' => $d['paternal_surname'], 'maternal_surname' => $d['maternal_surname'],
                    'curp' => $d['curp'], 'birth_date' => $d['birth_date'], 'gender' => $d['gender'], 'email' => $d['email'], 'phone' => $d['phone'],
                    'postal_code' => $d['postal_code'], 'address' => $d['address'],
                    'status' => $r['group_id'] && $authorize ? 'activo' : 'aspirante',
                ]);
                $student->health()->create([]);
                $created++;

                if ($r['group_id']) {
                    $g = Group::find($r['group_id']);
                    Enrollment::create([
                        'student_id' => $student->id, 'group_id' => $g->id, 'school_year_id' => $g->school_year_id, 'type' => 'inscripcion',
                        'status' => $authorize ? 'autorizada' : 'pendiente', 'enrolled_on' => now()->toDateString(),
                        'authorized_by' => $authorize ? auth()->id() : null, 'authorized_at' => $authorize ? now() : null,
                    ]);
                    $enrolled++;
                }
                if ($d['guardian_name']) {
                    // "NOMBRE(S) PATERNO MATERNO": los dos últimos son apellidos (si hay al menos 3 palabras)
                    $parts = preg_split('/\s+/', trim($d['guardian_name']));
                    $n = count($parts);
                    [$gName, $gPat, $gMat] = $n >= 3 ? [implode(' ', array_slice($parts, 0, $n - 2)), $parts[$n - 2], $parts[$n - 1]]
                        : ($n === 2 ? [$parts[0], $parts[1], null] : [$parts[0], '', null]);
                    $guardian = ($d['guardian_email'] ? Guardian::where('email', $d['guardian_email'])->first() : null) ?? Guardian::create([
                        'name' => $gName, 'paternal_surname' => $gPat, 'maternal_surname' => $gMat,
                        'phone' => $d['guardian_phone'], 'email' => $d['guardian_email'],
                    ]);
                    $student->guardians()->syncWithoutDetaching([$guardian->id => ['relationship' => $d['guardian_relationship'], 'is_primary' => true]]);
                    $guardians++;
                }
            }
        });
        \Illuminate\Support\Facades\Storage::disk('local')->delete($path);
        session()->forget('student_import');

        return redirect()->route('alumnos.index')->with('success', "Importación terminada: {$created} alumno(s), {$enrolled} inscripción(es) y {$guardians} tutor(es).");
    }

    /** Lee y valida cada fila. */
    private function analyze(?string $unused, string $path): array
    {
        $sheet = Excel::toArray(null, \Illuminate\Support\Facades\Storage::disk('local')->path($path))[0] ?? [];
        array_shift($sheet); // encabezados
        $groups = Group::with('grade')->where('school_year_id', SchoolYear::current()?->id)->get()
            ->keyBy(fn ($g) => $this->norm($g->label));
        $curps = Student::withoutGlobalScopes()->whereNotNull('curp')->pluck('curp')->map(fn ($c) => strtoupper($c))->flip();
        $seen = [];
        $out = [];

        foreach ($sheet as $i => $raw) {
            if (! array_filter($raw, fn ($v) => trim((string) $v) !== '')) {
                continue; // fila vacía
            }
            $d = [];
            foreach (self::COLUMNS as $c => [, $key]) {
                $d[$key] = trim((string) ($raw[$c] ?? '')) ?: null;
            }
            $errors = [];
            foreach (['name', 'paternal_surname', 'maternal_surname', 'guardian_name'] as $k) {
                if ($d[$k]) $d[$k] = mb_strtoupper($d[$k]);
            }
            if (! $d['name']) $errors[] = 'Falta el nombre';
            if (! $d['paternal_surname']) $errors[] = 'Falta el apellido paterno';
            if ($d['curp']) {
                $d['curp'] = strtoupper($d['curp']);
                if (! preg_match('/^[A-Z]{4}\d{6}[HMX][A-Z]{5}[A-Z0-9]\d$/', $d['curp'])) $errors[] = 'CURP con formato inválido';
                elseif (isset($curps[$d['curp']])) $errors[] = 'La CURP ya está registrada';
                elseif (isset($seen[$d['curp']])) $errors[] = 'CURP repetida en el archivo';
                $seen[$d['curp']] = true;
            }
            if ($d['birth_date']) {
                $d['birth_date'] = $this->date($raw[4] ?? $d['birth_date']);
                if (! $d['birth_date']) $errors[] = 'Fecha de nacimiento inválida (usa DD/MM/AAAA)';
            }
            if ($d['gender']) {
                $g = strtoupper(mb_substr($d['gender'], 0, 1));
                $d['gender'] = ['M' => 'F', 'F' => 'F', 'H' => 'M', 'X' => 'X'][$g] ?? null; // M = mujer, H = hombre
                if (! $d['gender']) $errors[] = 'Sexo inválido (M, H o X)';
            }
            // Teléfonos: solo números, 10 dígitos (se ignoran espacios, guiones y paréntesis)
            foreach (['phone' => 'Teléfono', 'guardian_phone' => 'Teléfono del tutor'] as $k => $label) {
                if ($d[$k]) {
                    $d[$k] = preg_replace('/[\s\-().]/', '', $d[$k]);
                    if (! preg_match('/^\d{10}$/', $d[$k])) $errors[] = "{$label} inválido (deben ser 10 dígitos)";
                }
            }
            if ($d['email'] && ! filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Correo inválido';
            if ($d['guardian_email'] && ! filter_var($d['guardian_email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Correo del tutor inválido';
            $d['guardian_relationship'] = in_array(mb_strtolower((string) $d['guardian_relationship']), array_keys(Guardian::RELATIONSHIPS), true)
                ? mb_strtolower($d['guardian_relationship']) : 'tutor';
            $groupId = null;
            if ($d['group']) {
                $grp = $groups[$this->norm($d['group'])] ?? null;
                $grp ? $groupId = $grp->id : $errors[] = "No existe el grupo «{$d['group']}» en el ciclo actual de este plantel";
            }

            $out[] = [
                'row' => $i + 2, 'ok' => ! $errors, 'errors' => $errors, 'group_id' => $groupId,
                'name' => trim(($d['paternal_surname'] ?? '') . ' ' . ($d['maternal_surname'] ?? '') . ', ' . ($d['name'] ?? '')),
                'curp' => $d['curp'], 'group' => $d['group'], 'guardian' => $d['guardian_name'], 'data' => $d,
            ];
        }
        return $out;
    }

    private function date($v): ?string
    {
        try {
            if (is_numeric($v)) return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $v))->toDateString();
            $v = trim((string) $v);
            foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd/m/y'] as $f) {
                $c = Carbon::createFromFormat($f, $v);
                if ($c && $c->format($f) === $v) return $c->toDateString();
            }
        } catch (\Throwable $e) {
        }
        return null;
    }

    private function norm(string $s): string
    {
        return Str::of($s)->lower()->replace(['°', 'º', ' '], '')->ascii()->toString();
    }

    private function nextEnrollmentNumber(): string
    {
        $prefix = now()->format('y');
        $last = Student::withTrashed()->withoutGlobalScope('campus')->where('enrollment_number', 'like', $prefix . '%')->max('enrollment_number');
        $n = $last ? ((int) substr($last, 2)) + 1 : 1;
        return $prefix . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
