<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\Attendance;
use App\Models\Campus;
use App\Models\Charge;
use App\Models\DocumentType;
use App\Models\Enrollment;
use App\Models\EvaluationCriterion;
use App\Models\Grade;
use App\Models\GradeRecord;
use App\Models\Group;
use App\Models\GroupSubject;
use App\Models\Guardian;
use App\Models\Incident;
use App\Models\Institution;
use App\Models\Payment;
use App\Models\Period;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\Scholarship;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Submission;
use App\Models\Teacher;
use App\Models\TutoringSession;
use App\Models\User;
use App\Support\Tenant;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Institución de demostración con datos de prueba.
 *
 * Cuentas demo (contraseña para todas: Sigue2026!):
 *   superadmin@sigue.mx     Administrador general SIGUE
 *   director@demo.sigue.mx  Director
 *   admin@demo.sigue.mx     Administrador institucional
 *   docente@demo.sigue.mx   Docente
 *   tutor@demo.sigue.mx     Padre o tutor
 *   alumno@demo.sigue.mx    Alumno
 */
class DemoInstitutionSeeder extends Seeder
{
    public const DEMO_PASSWORD = self::PASSWORD;
    private const PASSWORD = 'Sigue2026!';

    private array $names = ['SOFÍA', 'MATEO', 'VALENTINA', 'SANTIAGO', 'REGINA', 'LEONARDO', 'XIMENA', 'DIEGO', 'CAMILA', 'EMILIANO',
        'RENATA', 'SEBASTIÁN', 'MARÍA JOSÉ', 'DANIEL', 'NATALIA', 'ÁNGEL', 'FERNANDA', 'IKER', 'ISABELLA', 'ALEJANDRO',
        'MARIANA', 'GAEL', 'ANA PAULA', 'JULIÁN', 'VICTORIA', 'RODRIGO', 'PAULINA', 'TADEO', 'ANDREA', 'EMILIO', 'LUCÍA', 'HUGO'];
    private array $surnames = ['GARCÍA', 'HERNÁNDEZ', 'MARTÍNEZ', 'LÓPEZ', 'GONZÁLEZ', 'PÉREZ', 'RODRÍGUEZ', 'SÁNCHEZ', 'RAMÍREZ',
        'CRUZ', 'FLORES', 'GÓMEZ', 'MORALES', 'VÁZQUEZ', 'REYES', 'JIMÉNEZ', 'TORRES', 'DÍAZ', 'GUTIÉRREZ', 'RUIZ', 'MENDOZA',
        'AGUILAR', 'ORTIZ', 'CASTILLO', 'ROMERO', 'VEGA', 'ESTRADA', 'NAVARRO', 'SALAZAR', 'CHÁVEZ'];
    private array $adultNames = ['ROBERTO', 'PATRICIA', 'JORGE', 'GABRIELA', 'MIGUEL', 'LAURA', 'RICARDO', 'ADRIANA', 'FRANCISCO',
        'VERÓNICA', 'JAVIER', 'CLAUDIA', 'EDUARDO', 'MÓNICA', 'ARTURO', 'SILVIA', 'RAÚL', 'ELENA'];

    public function run(): void
    {
        mt_srand(2026);

        $super = $this->user(null, 'superadmin@sigue.mx', 'ADMINISTRADOR', 'SIGUE', null, Role::SUPERADMIN);

        $inst = Institution::updateOrCreate(['slug' => 'colegio-demo'], [
            'name' => 'COLEGIO DEMO SIGUE',
            'short_name' => 'Colegio Demo',
            'cct' => '09PES0000X',
            'email' => 'contacto@demo.sigue.mx',
            'phone' => '5555555555',
            'address' => 'AV. EDUCACIÓN 100, CIUDAD DE MÉXICO',
            'plan' => 'profesional',
            'max_students' => 500,
            'max_campuses' => 3,
            'license_expires_at' => now()->addYear()->toDateString(),
            'status' => 'activa',
        ]);

        if (Student::withoutGlobalScopes()->where('institution_id', $inst->id)->exists()) {
            $this->command?->warn('La institución demo ya tiene datos; se omite la carga.');
            return;
        }

        Tenant::run($inst->id, function () use ($inst) {
            $this->seedInstitution($inst);
        });
    }

    private function user(?int $institutionId, string $email, string $name, ?string $paternal, ?string $maternal, string $role): User
    {
        $u = User::withTrashed()->updateOrCreate(['email' => $email], [
            'institution_id' => $institutionId,
            'name' => $name,
            'paternal_surname' => $paternal,
            'maternal_surname' => $maternal,
            'password' => self::PASSWORD,
            'is_active' => true,
            'deleted_at' => null,
        ]);
        $u->roles()->syncWithoutDetaching([Role::where('slug', $role)->value('id')]);
        return $u;
    }

    private function pick(array $arr): string
    {
        return $arr[mt_rand(0, count($arr) - 1)];
    }

    private function seedInstitution(Institution $inst): void
    {
        $campus = Campus::create(['name' => 'PLANTEL CENTRO', 'code' => 'CEN', 'address' => $inst->address, 'is_active' => true]);
        Campus::create(['name' => 'PLANTEL NORTE', 'code' => 'NOR', 'is_active' => true]);

        $this->user($inst->id, 'director@demo.sigue.mx', 'LAURA', 'MENDOZA', 'RÍOS', Role::DIRECTOR);
        $this->user($inst->id, 'admin@demo.sigue.mx', 'CARLOS', 'NAVARRO', 'LUNA', Role::ADMIN);

        foreach (['ACTA DE NACIMIENTO', 'CURP', 'CERTIFICADO DE ESTUDIOS ANTERIOR', 'COMPROBANTE DE DOMICILIO', 'FOTOGRAFÍA INFANTIL'] as $i => $d) {
            DocumentType::create(['name' => $d, 'is_required' => $i < 4]);
        }

        // Ciclo escolar y periodos
        $year = SchoolYear::create(['name' => '2026-2027', 'starts_on' => '2026-08-24', 'ends_on' => '2027-07-09', 'is_current' => true, 'status' => 'abierto']);
        $periods = collect([
            ['TRIMESTRE 1', '2026-08-24', '2026-11-27'],
            ['TRIMESTRE 2', '2026-11-30', '2027-03-12'],
            ['TRIMESTRE 3', '2027-03-15', '2027-07-09'],
        ])->map(fn ($p, $i) => Period::create(['school_year_id' => $year->id, 'name' => $p[0], 'order' => $i + 1, 'starts_on' => $p[1], 'ends_on' => $p[2]]));
        $p1 = $periods[0];

        $grades = collect(['1°', '2°', '3°'])->map(fn ($g, $i) => Grade::create(['name' => $g, 'level' => 'Secundaria', 'order' => $i + 1]));

        $subjectNames = ['ESPAÑOL', 'MATEMÁTICAS', 'CIENCIAS', 'HISTORIA', 'INGLÉS', 'EDUCACIÓN FÍSICA'];
        $subjects = [];
        foreach ($grades as $g) {
            foreach ($subjectNames as $k => $s) {
                $subjects[$g->id][] = Subject::create(['grade_id' => $g->id, 'name' => $s, 'code' => strtoupper(substr($s, 0, 3)) . $g->order, 'hours_per_week' => $k < 2 ? 5 : 3]);
            }
        }

        // Docentes
        // [nombre, paterno, materno, especialidad, sexo, grado, carrera, institución de egreso]
        $teacherData = [
            ['ANA', 'TORRES', 'VEGA', 'Lengua y literatura', 'F', 'maestria', 'Maestría en Letras Hispánicas', 'UNAM'],
            ['JORGE', 'RAMÍREZ', 'CRUZ', 'Matemáticas', 'M', 'doctorado', 'Doctorado en Matemática Educativa', 'CINVESTAV'],
            ['ELENA', 'SALAZAR', 'DÍAZ', 'Ciencias naturales', 'F', 'licenciatura', 'Licenciatura en Biología', 'UAM'],
            ['MARIO', 'GÓMEZ', 'ORTIZ', 'Historia', 'M', 'normal', 'Licenciatura en Educación Secundaria (Historia)', 'Escuela Normal Superior de México'],
            ['SARA', 'FLORES', 'REYES', 'Inglés', 'F', 'licenciatura', 'Licenciatura en Enseñanza del Inglés', 'UPN'],
            ['LUIS', 'CHÁVEZ', 'MORA', 'Educación física', 'M', 'licenciatura', 'Licenciatura en Educación Física', 'ESEF'],
        ];
        $teachers = [];
        foreach ($teacherData as $i => [$n, $p, $m, $spec, $gender, $degree, $career, $school]) {
            $email = $i === 0 ? 'docente@demo.sigue.mx' : 'docente' . ($i + 1) . '@demo.sigue.mx';
            $u = $this->user($inst->id, $email, $n, $p, $m, Role::DOCENTE);
            $teachers[] = Teacher::create([
                'campus_id' => $campus->id, 'user_id' => $u->id, 'employee_number' => 'D' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'name' => $n, 'paternal_surname' => $p, 'maternal_surname' => $m, 'email' => $email,
                'phone' => '55' . mt_rand(10000000, 99999999), 'specialty' => $spec, 'hired_on' => '2020-08-01', 'status' => 'activo',
                'gender' => $gender, 'degree' => $degree, 'career' => $career, 'alma_mater' => $school,
                'professional_license' => (string) mt_rand(10000000, 12999999),
            ]);
        }

        // Grupos: 1°A, 1°B, 2°A, 3°A
        $groupDefs = [[$grades[0], 'A'], [$grades[0], 'B'], [$grades[1], 'A'], [$grades[2], 'A']];
        $groups = [];
        foreach ($groupDefs as $i => [$g, $name]) {
            $groups[] = Group::create([
                'campus_id' => $campus->id, 'school_year_id' => $year->id, 'grade_id' => $g->id, 'name' => $name,
                'shift' => 'matutino', 'capacity' => 35, 'tutor_teacher_id' => $teachers[$i % count($teachers)]->id,
            ]);
        }

        // Clases (asignatura + docente) y horarios
        $classes = [];
        foreach ($groups as $gi => $group) {
            foreach ($subjects[$group->grade_id] as $k => $subject) {
                $gs = GroupSubject::create(['group_id' => $group->id, 'subject_id' => $subject->id, 'teacher_id' => $teachers[$k]->id]);
                $classes[$group->id][] = $gs;
                $days = $k < 2 ? [1, 2, 3, 4, 5] : [($k % 5) + 1, (($k + 2) % 5) + 1];
                foreach ($days as $d) {
                    // Cada grupo rota el orden de las materias: un docente no tiene dos grupos a la misma hora.
                    $start = 7 + (($k + $gi) % count($subjects[$group->grade_id]));
                    Schedule::create(['group_subject_id' => $gs->id, 'day_of_week' => $d, 'starts_at' => sprintf('%02d:00', $start), 'ends_at' => sprintf('%02d:50', $start), 'room' => 'AULA ' . ($group->id + 100)]);
                }
            }
        }

        // Alumnos, tutores e inscripciones
        $guardianIndex = 0;
        $studentNo = 0;
        $allStudents = [];
        foreach ($groups as $group) {
            for ($i = 0; $i < 10; $i++) {
                $studentNo++;
                $pat = $this->pick($this->surnames);
                $mat = $this->pick($this->surnames);
                $birthYear = 2014 - ($group->grade->order ?? 1);
                $student = Student::create([
                    'campus_id' => $campus->id,
                    'enrollment_number' => '26' . str_pad((string) $studentNo, 4, '0', STR_PAD_LEFT),
                    'name' => $this->names[($studentNo - 1) % count($this->names)],
                    'paternal_surname' => $pat, 'maternal_surname' => $mat,
                    'birth_date' => Carbon::create($birthYear, mt_rand(1, 12), mt_rand(1, 28)),
                    'gender' => $studentNo % 2 ? 'F' : 'M',
                    'address' => 'CALLE ' . mt_rand(1, 99) . ' #' . mt_rand(1, 400) . ', CDMX',
                    'postal_code' => '0' . mt_rand(1000, 9999),
                    'emergency_contact_name' => $this->pick($this->adultNames) . ' ' . $pat,
                    'emergency_contact_phone' => '55' . mt_rand(10000000, 99999999),
                    'status' => 'activo',
                ]);
                $student->health()->create([
                    'blood_type' => $this->pick(['O', 'A', 'B', 'AB']), 'rh_factor' => mt_rand(0, 9) ? 'Positivo' : 'Negativo',
                    'weight_kg' => mt_rand(38, 60), 'height_cm' => mt_rand(140, 170),
                    'allergies' => mt_rand(0, 5) ? null : 'PENICILINA', 'consent_at' => now(), 'consent_by' => 'Tutor',
                ]);

                $guardianIndex++;
                $gEmail = $guardianIndex === 1 ? 'tutor@demo.sigue.mx' : null;
                $gUser = $gEmail ? $this->user(Tenant::id(), $gEmail, 'ROBERTO', $pat, $this->pick($this->surnames), Role::TUTOR) : null;
                $guardian = Guardian::create([
                    'campus_id' => $campus->id,
                    'user_id' => $gUser?->id,
                    'name' => $gUser ? 'ROBERTO' : $this->pick($this->adultNames), 'paternal_surname' => $pat,
                    'maternal_surname' => $gUser?->maternal_surname ?? $this->pick($this->surnames),
                    'email' => $gEmail ?? 'tutor' . $guardianIndex . '@correo.mx', 'phone' => '55' . mt_rand(10000000, 99999999),
                    'occupation' => $this->pick(['EMPLEADO', 'COMERCIANTE', 'DOCENTE', 'INGENIERO', 'MÉDICO', 'HOGAR']),
                    'identity_verified_at' => now(),
                ]);
                $student->guardians()->attach($guardian->id, ['relationship' => $studentNo % 2 ? 'padre' : 'madre', 'is_primary' => true]);

                if ($studentNo === 1) {
                    $u = $this->user(Tenant::id(), 'alumno@demo.sigue.mx', $student->name, $pat, $mat, Role::ALUMNO);
                    $student->update(['user_id' => $u->id, 'email' => $u->email]);
                }

                Enrollment::create([
                    'student_id' => $student->id, 'group_id' => $group->id, 'school_year_id' => $year->id,
                    'type' => $group->grade->order > 1 ? 'reinscripcion' : 'inscripcion', 'status' => 'autorizada',
                    'enrolled_on' => '2026-08-10', 'authorized_at' => now(),
                ]);
                $allStudents[$group->id][] = $student;
            }
        }
        // El tutor demo también tiene a un segundo hijo (1° B).
        $tutorDemo = Guardian::where('email', 'tutor@demo.sigue.mx')->first();
        $tutorDemo?->students()->syncWithoutDetaching([$allStudents[$groups[1]->id][0]->id => ['relationship' => 'padre', 'is_primary' => true]]);

        // Asistencias de las últimas 3 semanas, criterios, tareas y calificaciones del trimestre 1
        $days = collect();
        for ($d = Carbon::today()->subDays(21); $d->lte(Carbon::today()); $d->addDay()) {
            if ($d->isWeekday()) {
                $days->push($d->copy());
            }
        }

        foreach ($groups as $group) {
            $students = $allStudents[$group->id];
            foreach ($classes[$group->id] as $k => $gs) {
                $teacherUser = $gs->teacher->user_id;
                foreach ($days as $day) {
                    if (! $gs->schedules->contains('day_of_week', $day->dayOfWeekIso)) {
                        continue;
                    }
                    foreach ($students as $s) {
                        $r = mt_rand(1, 100);
                        Attendance::create([
                            'group_subject_id' => $gs->id, 'student_id' => $s->id, 'date' => $day->toDateString(), 'session' => 1,
                            'status' => $r <= 86 ? 'presente' : ($r <= 93 ? 'retardo' : ($r <= 98 ? 'falta' : 'justificada')),
                            'recorded_by' => $teacherUser,
                        ]);
                    }
                }

                $cTareas = EvaluationCriterion::create(['group_subject_id' => $gs->id, 'period_id' => $p1->id, 'name' => 'TAREAS', 'type' => 'tareas', 'source' => 'tareas', 'weight' => 30]);
                $cExamen = EvaluationCriterion::create(['group_subject_id' => $gs->id, 'period_id' => $p1->id, 'name' => 'EXAMEN TRIMESTRAL', 'type' => 'examenes', 'source' => 'manual', 'weight' => 50]);
                EvaluationCriterion::create(['group_subject_id' => $gs->id, 'period_id' => $p1->id, 'name' => 'ASISTENCIA', 'type' => 'asistencia', 'source' => 'asistencia', 'weight' => 20]);

                foreach ([['INVESTIGACIÓN', -12], ['EJERCICIOS DE REPASO', -5], ['PROYECTO INTEGRADOR', 4]] as $j => [$title, $offset]) {
                    $a = Assignment::create([
                        'group_subject_id' => $gs->id, 'period_id' => $p1->id, 'evaluation_criterion_id' => $cTareas->id,
                        'type' => $j === 2 ? 'proyecto' : 'tarea', 'title' => $title . ' · ' . $gs->subject->name,
                        'description' => 'Entregar en PDF siguiendo las indicaciones vistas en clase.',
                        'due_at' => Carbon::today()->addDays($offset)->setTime(23, 59), 'max_score' => 10, 'created_by' => $teacherUser,
                    ]);
                    if ($offset < 0) {
                        foreach ($students as $s) {
                            if (mt_rand(1, 100) > 88) {
                                continue;
                            }
                            Submission::create([
                                'assignment_id' => $a->id, 'student_id' => $s->id, 'content' => 'Entrega de la actividad.',
                                'submitted_at' => $a->due_at->copy()->subHours(mt_rand(1, 48)), 'status' => 'calificada',
                                'score' => mt_rand(60, 100) / 10, 'feedback' => 'Buen trabajo.', 'graded_by' => $teacherUser, 'graded_at' => now(),
                            ]);
                        }
                    }
                }

                foreach ($students as $s) {
                    GradeRecord::create(['group_subject_id' => $gs->id, 'period_id' => $p1->id, 'student_id' => $s->id,
                        'evaluation_criterion_id' => $cExamen->id, 'score' => mt_rand(55, 100) / 10, 'updated_by' => $teacherUser]);
                }
            }
        }

        // Incidencias y tutorías de ejemplo
        $sample = collect($allStudents)->flatten();
        foreach ($sample->random(8) as $i => $s) {
            $cat = array_keys(Incident::CATEGORIES)[$i % 7];
            Incident::create([
                'student_id' => $s->id, 'teacher_id' => $teachers[$i % 6]->id, 'reported_by' => $teachers[$i % 6]->user_id,
                'date' => Carbon::today()->subDays(mt_rand(0, 15)), 'category' => $cat,
                'description' => $cat === 'conducta_positiva' ? 'APOYÓ A SUS COMPAÑEROS DURANTE LA PRÁCTICA.' : 'SE REGISTRA PARA SEGUIMIENTO.',
                'priority' => $this->pick(['baja', 'media', 'alta']), 'status' => $this->pick(['abierta', 'seguimiento', 'cerrada']),
            ]);
        }
        foreach ($sample->random(3) as $s) {
            TutoringSession::create([
                'student_id' => $s->id, 'teacher_id' => $teachers[0]->id, 'date' => Carbon::today()->subDays(mt_rand(1, 10)),
                'topic' => 'SEGUIMIENTO ACADÉMICO', 'observations' => 'BAJO RENDIMIENTO EN MATEMÁTICAS.',
                'agreements' => 'ASISTIR A ASESORÍAS LOS MARTES.', 'follow_up_on' => Carbon::today()->addDays(7), 'status' => 'abierta',
            ]);
        }

        // Becas, adeudos y pagos
        $becados = $sample->random(3)->values();
        $becas = [];
        foreach ($becados as $i => $s) {
            $becas[$s->id] = Scholarship::create([
                'student_id' => $s->id, 'code' => 'BEC-' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'name' => $i === 0 ? 'BECA EXCELENCIA' : 'BECA APOYO FAMILIAR', 'type' => $i === 0 ? 'academica' : 'socioeconomica',
                'percentage' => $i === 0 ? 50 : 25, 'starts_on' => '2026-08-01', 'ends_on' => '2027-07-31',
                'conditions' => 'MANTENER PROMEDIO MÍNIMO DE 8.5', 'status' => 'activa',
            ]);
        }

        $receiver = User::where('email', 'admin@demo.sigue.mx')->value('id');
        $months = ['SEPTIEMBRE' => '2026-09-10', 'OCTUBRE' => '2026-10-10', 'NOVIEMBRE' => '2026-11-10', 'DICIEMBRE' => '2026-12-10'];
        foreach ($sample as $s) {
            $beca = $becas[$s->id] ?? null;
            $insc = Charge::create(['student_id' => $s->id, 'school_year_id' => $year->id, 'type' => 'inscripcion', 'concept' => 'INSCRIPCIÓN 2026-2027', 'amount' => 2500, 'due_on' => '2026-08-15']);
            $this->pay($insc, $receiver, '2026-08-12');
            foreach ($months as $m => $due) {
                $discount = $beca ? $beca->discountFor(3200) : 0;
                $c = Charge::create(['student_id' => $s->id, 'school_year_id' => $year->id, 'scholarship_id' => $beca?->id, 'type' => 'colegiatura',
                    'concept' => 'COLEGIATURA ' . $m, 'amount' => 3200, 'discount' => $discount, 'due_on' => $due]);
                if (Carbon::parse($due)->isPast() && mt_rand(1, 100) <= 85) {
                    $this->pay($c, $receiver, Carbon::parse($due)->subDays(mt_rand(0, 6))->toDateString());
                }
            }
            if (mt_rand(0, 1)) {
                Charge::create(['student_id' => $s->id, 'school_year_id' => $year->id, 'type' => 'cooperacion', 'concept' => 'COOPERACIÓN FESTIVAL DE INVIERNO', 'amount' => 150, 'due_on' => '2026-12-05']);
            }
        }
    }

    private function pay(Charge $charge, ?int $receiver, string $date): void
    {
        Payment::create([
            'student_id' => $charge->student_id, 'charge_id' => $charge->id, 'folio' => Payment::nextFolio(),
            'amount' => $charge->total(), 'method' => $this->pick(['efectivo', 'transferencia', 'tarjeta']),
            'paid_at' => Carbon::parse($date)->setTime(mt_rand(8, 14), mt_rand(0, 59)), 'received_by' => $receiver,
        ]);
        $charge->refreshStatus();
    }
}
