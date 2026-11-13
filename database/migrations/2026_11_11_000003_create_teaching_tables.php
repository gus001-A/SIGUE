<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SIGUE · Módulo docente: asistencias, classroom, evaluaciones,
 * calificaciones, incidencias y tutorías.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance', function (Blueprint $t) {
            $t->id();
            $t->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $t->foreignId('group_subject_id')->constrained()->cascadeOnDelete();
            $t->foreignId('student_id')->constrained()->cascadeOnDelete();
            $t->date('date');
            $t->unsignedTinyInteger('session')->default(1);
            $t->string('status', 20);                       // presente | falta | retardo | justificada
            $t->string('notes')->nullable();
            $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            // Regla DOC-ASIS-001: sin duplicados por alumno, clase y sesión.
            $t->unique(['group_subject_id', 'student_id', 'date', 'session'], 'attendance_unique_session');
        });

        Schema::create('evaluation_criteria', function (Blueprint $t) {
            $t->id();
            $t->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $t->foreignId('group_subject_id')->constrained()->cascadeOnDelete();
            $t->foreignId('period_id')->constrained()->cascadeOnDelete();
            $t->string('name', 80);
            $t->string('type', 30)->default('otro');        // tareas | examenes | proyectos | participacion | asistencia | puntualidad | actividades | otro
            $t->string('source', 20)->default('manual');    // manual | tareas | asistencia
            $t->decimal('weight', 5, 2)->default(0);        // %
            $t->timestamps();
        });

        Schema::create('assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $t->foreignId('group_subject_id')->constrained()->cascadeOnDelete();
            $t->foreignId('period_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('evaluation_criterion_id')->nullable()->constrained('evaluation_criteria')->nullOnDelete();
            $t->string('type', 20)->default('tarea');       // tarea | proyecto | actividad
            $t->string('title');
            $t->text('description')->nullable();
            $t->string('attachment_path')->nullable();
            $t->string('attachment_name')->nullable();
            $t->dateTime('due_at')->nullable();
            $t->decimal('max_score', 5, 2)->default(10);
            $t->boolean('is_published')->default(true);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('submissions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $t->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $t->foreignId('student_id')->constrained()->cascadeOnDelete();
            $t->text('content')->nullable();
            $t->string('file_path')->nullable();
            $t->string('file_name')->nullable();
            $t->timestamp('submitted_at')->nullable();
            $t->string('status', 20)->default('entregada');    // entregada | tarde | calificada | devuelta
            $t->decimal('score', 5, 2)->nullable();
            $t->text('feedback')->nullable();
            $t->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('graded_at')->nullable();
            $t->timestamps();
            $t->unique(['assignment_id', 'student_id']);
        });

        Schema::create('grades_records', function (Blueprint $t) {
            $t->id();
            $t->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $t->foreignId('group_subject_id')->constrained()->cascadeOnDelete();
            $t->foreignId('period_id')->constrained()->cascadeOnDelete();
            $t->foreignId('student_id')->constrained()->cascadeOnDelete();
            // null = calificación final del periodo (promedio ponderado).
            $t->foreignId('evaluation_criterion_id')->nullable()->constrained('evaluation_criteria')->cascadeOnDelete();
            $t->decimal('score', 5, 2)->nullable();
            $t->boolean('is_published')->default(false);
            $t->timestamp('published_at')->nullable();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['group_subject_id', 'period_id', 'student_id', 'evaluation_criterion_id'], 'grades_records_unique');
        });

        Schema::create('incidents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $t->foreignId('student_id')->constrained()->cascadeOnDelete();
            $t->foreignId('teacher_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('group_subject_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $t->date('date');
            $t->string('category', 40);                     // conducta_positiva | conducta_negativa | retardo | incumplimiento | participacion | observacion | emocional
            $t->text('description');
            $t->string('evidence_path')->nullable();
            $t->string('priority', 10)->default('media');   // baja | media | alta
            $t->boolean('notify_guardian')->default(true);
            $t->string('status', 20)->default('abierta');   // abierta | seguimiento | cerrada
            $t->text('follow_up')->nullable();
            $t->timestamps();
        });

        Schema::create('tutoring_sessions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $t->foreignId('student_id')->constrained()->cascadeOnDelete();
            $t->foreignId('teacher_id')->nullable()->constrained()->nullOnDelete();
            $t->date('date');
            $t->string('topic');
            $t->text('observations')->nullable();
            $t->text('agreements')->nullable();             // compromisos
            $t->text('results')->nullable();
            $t->date('follow_up_on')->nullable();           // alerta de seguimiento
            $t->string('status', 20)->default('abierta');   // abierta | cerrada
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['tutoring_sessions', 'incidents', 'grades_records', 'submissions', 'assignments',
                  'evaluation_criteria', 'attendance'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
