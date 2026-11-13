<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SIGUE · Estructura académica, comunidad escolar y matriculación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_years', function (Blueprint $t) {
            $t->id();
            $t->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $t->string('name', 40);                         // 2026-2027
            $t->date('starts_on');
            $t->date('ends_on');
            $t->boolean('is_current')->default(false);
            $t->string('status', 20)->default('abierto');   // planeacion | abierto | cerrado
            $t->timestamps();
        });

        Schema::create('periods', function (Blueprint $t) {
            $t->id();
            $t->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $t->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $t->string('name', 60);                         // Parcial 1, Trimestre 1...
            $t->unsignedTinyInteger('order')->default(1);
            $t->date('starts_on')->nullable();
            $t->date('ends_on')->nullable();
            $t->boolean('is_closed')->default(false);
            $t->timestamps();
        });

        Schema::create('grades', function (Blueprint $t) {     // grados escolares
            $t->id();
            $t->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $t->string('name', 60);                         // 1°, 2°, 1er semestre...
            $t->string('level', 40)->nullable();            // Preescolar | Primaria | Secundaria | Bachillerato | Licenciatura
            $t->unsignedTinyInteger('order')->default(1);
            $t->timestamps();
        });

        Schema::create('teachers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $t->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('employee_number', 30)->nullable();
            $t->string('name');
            $t->string('paternal_surname')->nullable();
            $t->string('maternal_surname')->nullable();
            $t->string('email')->nullable();
            $t->string('phone', 20)->nullable();
            $t->string('curp', 18)->nullable();
            $t->string('rfc', 13)->nullable();
            $t->string('specialty')->nullable();
            $t->date('hired_on')->nullable();
            $t->string('status', 20)->default('activo');
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('groups', function (Blueprint $t) {
            $t->id();
            $t->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $t->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $t->foreignId('grade_id')->constrained()->cascadeOnDelete();
            $t->foreignId('tutor_teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $t->string('name', 30);                         // A, B, 101...
            $t->string('shift', 20)->default('matutino');
            $t->unsignedSmallInteger('capacity')->nullable();
            $t->timestamps();
        });

        Schema::create('subjects', function (Blueprint $t) {
            $t->id();
            $t->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $t->foreignId('grade_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name');
            $t->string('code', 30)->nullable();
            $t->unsignedTinyInteger('hours_per_week')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        // Asignatura impartida en un grupo por un docente ("clase").
        Schema::create('group_subjects', function (Blueprint $t) {
            $t->id();
            $t->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $t->foreignId('group_id')->constrained()->cascadeOnDelete();
            $t->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $t->foreignId('teacher_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
            $t->unique(['group_id', 'subject_id']);
        });

        Schema::create('schedules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $t->foreignId('group_subject_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('day_of_week');         // 1 = lunes … 7 = domingo
            $t->time('starts_at');
            $t->time('ends_at');
            $t->string('room', 40)->nullable();
            $t->timestamps();
        });

        Schema::create('students', function (Blueprint $t) {
            $t->id();
            $t->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $t->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('enrollment_number', 30)->nullable();      // matrícula
            $t->string('name');
            $t->string('paternal_surname')->nullable();
            $t->string('maternal_surname')->nullable();
            $t->date('birth_date')->nullable();
            $t->string('curp', 18)->nullable();
            $t->string('gender', 10)->nullable();
            $t->string('email')->nullable();
            $t->string('phone', 20)->nullable();
            $t->string('address')->nullable();
            $t->string('postal_code', 10)->nullable();
            $t->string('photo_path')->nullable();
            $t->boolean('photo_publication_consent')->default(false);
            $t->string('emergency_contact_name')->nullable();
            $t->string('emergency_contact_phone', 20)->nullable();
            $t->string('status', 20)->default('activo');    // aspirante | activo | baja | egresado | titulado
            $t->date('graduated_on')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['institution_id', 'enrollment_number']);
        });

        // Expediente de salud (acceso restringido por permiso).
        Schema::create('student_health', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('blood_type', 5)->nullable();
            $t->string('rh_factor', 10)->nullable();
            $t->decimal('weight_kg', 5, 2)->nullable();
            $t->decimal('height_cm', 5, 2)->nullable();
            $t->text('allergies')->nullable();
            $t->text('medical_notes')->nullable();
            $t->timestamp('consent_at')->nullable();
            $t->string('consent_by')->nullable();
            $t->timestamps();
        });

        Schema::create('guardians', function (Blueprint $t) {
            $t->id();
            $t->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name');
            $t->string('paternal_surname')->nullable();
            $t->string('maternal_surname')->nullable();
            $t->string('email')->nullable();
            $t->string('phone', 20)->nullable();
            $t->string('curp', 18)->nullable();
            $t->string('occupation')->nullable();
            $t->string('address')->nullable();
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->boolean('location_consent')->default(false);
            $t->timestamp('identity_verified_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('student_guardians', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_id')->constrained()->cascadeOnDelete();
            $t->foreignId('guardian_id')->constrained()->cascadeOnDelete();
            $t->string('relationship', 30)->default('tutor');   // padre | madre | tutor | otro
            $t->boolean('is_primary')->default(false);
            $t->boolean('can_pickup')->default(true);
            $t->timestamps();
            $t->unique(['student_id', 'guardian_id']);
        });

        Schema::create('enrollments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $t->foreignId('student_id')->constrained()->cascadeOnDelete();
            $t->foreignId('group_id')->constrained()->cascadeOnDelete();
            $t->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $t->string('type', 20)->default('inscripcion');     // inscripcion | reinscripcion
            $t->string('status', 20)->default('pendiente');     // pendiente | autorizada | baja | concluida
            $t->date('enrolled_on')->nullable();
            $t->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('authorized_at')->nullable();
            $t->date('dropped_on')->nullable();
            $t->string('drop_reason')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['student_id', 'school_year_id']);
        });

        Schema::create('document_types', function (Blueprint $t) {
            $t->id();
            $t->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->boolean('is_required')->default(true);
            $t->timestamps();
        });

        Schema::create('documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $t->foreignId('student_id')->constrained()->cascadeOnDelete();
            $t->foreignId('document_type_id')->nullable()->constrained()->nullOnDelete();
            $t->string('title');
            $t->string('file_path');                             // disco privado
            $t->string('original_name')->nullable();
            $t->string('mime', 100)->nullable();
            $t->unsignedInteger('size')->nullable();
            $t->string('status', 20)->default('pendiente');      // pendiente | validado | rechazado | correccion
            $t->text('review_notes')->nullable();
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['documents', 'document_types', 'enrollments', 'student_guardians', 'guardians', 'student_health',
                  'students', 'schedules', 'group_subjects', 'subjects', 'groups', 'teachers', 'grades',
                  'periods', 'school_years'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
