<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Formación del docente: grado de estudios (para el título Lic., Mtro., Dr.…), carrera, institución y cédula. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teachers', function (Blueprint $t) {
            if (! Schema::hasColumn('teachers', 'gender')) $t->char('gender', 1)->nullable()->after('maternal_surname');
            if (! Schema::hasColumn('teachers', 'degree')) $t->string('degree', 20)->nullable()->after('specialty');
            if (! Schema::hasColumn('teachers', 'career')) $t->string('career', 180)->nullable()->after('degree');
            if (! Schema::hasColumn('teachers', 'alma_mater')) $t->string('alma_mater', 180)->nullable()->after('career');
            if (! Schema::hasColumn('teachers', 'professional_license')) $t->string('professional_license', 20)->nullable()->after('alma_mater');
        });
    }

    public function down(): void
    {
        Schema::table('teachers', fn (Blueprint $t) => $t->dropColumn(['gender', 'degree', 'career', 'alma_mater', 'professional_license']));
    }
};
