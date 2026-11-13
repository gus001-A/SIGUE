<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Las tareas también adjuntan enlaces (YouTube, Drive, sitios web), como en Google Classroom. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', fn (Blueprint $t) => $t->json('links')->nullable()->after('files'));
    }

    public function down(): void
    {
        Schema::table('assignments', fn (Blueprint $t) => $t->dropColumn('links'));
    }
};
