<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SIGUE ya no maneja carreras: se eliminan la tabla `careers` y las columnas
 * `career_id` de grupos, asignaturas y alumnos en bases que ya las tenían.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['groups', 'subjects', 'students'] as $table) {
            if (Schema::hasColumn($table, 'career_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropConstrainedForeignId('career_id');
                });
            }
        }

        Schema::dropIfExists('careers');
    }

    public function down(): void
    {
        // Sin reversa: las carreras se retiraron del sistema.
    }
};
