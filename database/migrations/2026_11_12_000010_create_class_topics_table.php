<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Temas del Classroom: el docente agrupa las tareas de su clase (p. ej. "Verbos en participio"). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_topics', function (Blueprint $t) {
            $t->id();
            $t->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $t->foreignId('group_subject_id')->constrained()->cascadeOnDelete();
            $t->string('name', 120);
            $t->unsignedSmallInteger('position')->default(0);
            $t->timestamps();
            $t->softDeletes();
            $t->index(['group_subject_id', 'position']);
        });
        Schema::table('assignments', fn (Blueprint $t) => $t->foreignId('topic_id')->nullable()->after('period_id')->constrained('class_topics')->nullOnDelete());
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $t) {
            $t->dropConstrainedForeignId('topic_id');
        });
        Schema::dropIfExists('class_topics');
    }
};
