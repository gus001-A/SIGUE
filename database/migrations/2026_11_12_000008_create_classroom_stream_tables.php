<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Classroom estilo Google:
 *  - class_posts: avisos y material de la clase (archivos y enlaces: diapositivas, PDF, videos…).
 *  - classroom_comments: comentarios de la clase (todos los ven) y privados (docente ↔ alumno) en tareas y avisos.
 *  - assignments.rubric / submissions.rubric_scores: rúbrica que configura el docente y nivel obtenido por criterio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_posts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $t->foreignId('group_subject_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('type', 20)->default('aviso');          // aviso | material
            $t->string('title', 200)->nullable();
            $t->text('body')->nullable();
            $t->json('files')->nullable();
            $t->json('links')->nullable();                     // [{url, title}]
            $t->boolean('pinned')->default(false);
            $t->timestamps();
            $t->softDeletes();
            $t->index(['group_subject_id', 'created_at']);
        });

        Schema::create('classroom_comments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $t->foreignId('group_subject_id')->constrained()->cascadeOnDelete();
            $t->string('commentable_type', 20);                // assignment | post
            $t->unsignedBigInteger('commentable_id');
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->boolean('is_private')->default(false);
            $t->foreignId('student_id')->nullable()->constrained()->cascadeOnDelete(); // dueño del hilo privado
            $t->text('body');
            $t->timestamps();
            $t->softDeletes();
            $t->index(['commentable_type', 'commentable_id']);
        });

        Schema::table('assignments', fn (Blueprint $t) => $t->json('rubric')->nullable()->after('files'));
        Schema::table('submissions', fn (Blueprint $t) => $t->json('rubric_scores')->nullable()->after('score'));
    }

    public function down(): void
    {
        Schema::table('submissions', fn (Blueprint $t) => $t->dropColumn('rubric_scores'));
        Schema::table('assignments', fn (Blueprint $t) => $t->dropColumn('rubric'));
        Schema::dropIfExists('classroom_comments');
        Schema::dropIfExists('class_posts');
    }
};
