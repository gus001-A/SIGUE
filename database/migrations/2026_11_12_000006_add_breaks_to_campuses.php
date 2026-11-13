<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Receso por plantel y turno: {"matutino": {"start": "10:30", "end": "11:00"}, "vespertino": {...}}.
 * Se aplica a todos los grados y grupos del plantel según su turno.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('campuses', 'breaks')) {
            Schema::table('campuses', fn (Blueprint $t) => $t->json('breaks')->nullable()->after('phone'));
        }
    }

    public function down(): void
    {
        Schema::table('campuses', fn (Blueprint $t) => $t->dropColumn('breaks'));
    }
};
