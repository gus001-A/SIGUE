<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Los padres y tutores pertenecen a un solo plantel, igual que los alumnos:
 * no se comparten entre planteles. Se toma el plantel de sus alumnos vinculados
 * (o el primer plantel de la institución si no tiene alumnos).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('guardians', 'campus_id')) {
            Schema::table('guardians', function (Blueprint $t) {
                $t->foreignId('campus_id')->nullable()->after('institution_id')->constrained()->nullOnDelete();
            });
        }

        DB::table('guardians')->whereNull('campus_id')->orderBy('id')->each(function ($g) {
            $campus = DB::table('student_guardians as sg')->join('students as s', 's.id', '=', 'sg.student_id')
                ->where('sg.guardian_id', $g->id)->whereNotNull('s.campus_id')
                ->select('s.campus_id', DB::raw('count(*) as n'))->groupBy('s.campus_id')->orderByDesc('n')->value('s.campus_id')
                ?? DB::table('campuses')->where('institution_id', $g->institution_id)->orderBy('id')->value('id');
            DB::table('guardians')->where('id', $g->id)->update(['campus_id' => $campus]);
        });
    }

    public function down(): void
    {
        Schema::table('guardians', function (Blueprint $t) {
            $t->dropConstrainedForeignId('campus_id');
        });
    }
};
