<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo SEPOMEX de códigos postales y domicilio estructurado
 * (CP, colonia, municipio y estado) para instituciones, planteles, alumnos y tutores.
 * El campo `address` existente queda como "Calle y número".
 */
return new class extends Migration
{
    private const TABLES = ['institutions', 'campuses', 'students', 'guardians'];

    public function up(): void
    {
        if (! Schema::hasTable('codigos_postales')) {
            Schema::create('codigos_postales', function (Blueprint $t) {
                $t->id();
                $t->char('cp', 5)->index();
                $t->string('colonia', 120);
                $t->string('tipo_asentamiento', 40)->nullable();
                $t->string('municipio', 120);
                $t->string('estado', 60);
                $t->string('ciudad', 120)->nullable();
            });
        }

        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (! Schema::hasColumn($table, 'postal_code')) {
                    $t->string('postal_code', 10)->nullable()->after('address');
                }
                if (! Schema::hasColumn($table, 'colony')) {
                    $t->string('colony', 120)->nullable()->after('postal_code');
                }
                if (! Schema::hasColumn($table, 'municipality')) {
                    $t->string('municipality', 120)->nullable()->after('colony');
                }
                if (! Schema::hasColumn($table, 'state')) {
                    $t->string('state', 60)->nullable()->after('municipality');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $cols = array_filter(['colony', 'municipality', 'state'], fn ($c) => Schema::hasColumn($table, $c));
                if ($table !== 'students' && Schema::hasColumn($table, 'postal_code')) {
                    $cols[] = 'postal_code';
                }
                if ($cols) {
                    $t->dropColumn($cols);
                }
            });
        }
        Schema::dropIfExists('codigos_postales');
    }
};
