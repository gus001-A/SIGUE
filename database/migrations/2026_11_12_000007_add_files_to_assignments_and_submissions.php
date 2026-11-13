<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tareas y entregas admiten hasta 5 archivos: se guarda la lista en `files`
 * ([{path, name, size}]) y se migra el archivo único que ya existía.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', fn (Blueprint $t) => $t->json('files')->nullable()->after('attachment_name'));
        Schema::table('submissions', fn (Blueprint $t) => $t->json('files')->nullable()->after('file_name'));

        DB::table('assignments')->whereNotNull('attachment_path')->orderBy('id')->each(function ($a) {
            DB::table('assignments')->where('id', $a->id)->update(['files' => json_encode([['path' => $a->attachment_path, 'name' => $a->attachment_name ?? basename($a->attachment_path), 'size' => null]])]);
        });
        DB::table('submissions')->whereNotNull('file_path')->orderBy('id')->each(function ($s) {
            DB::table('submissions')->where('id', $s->id)->update(['files' => json_encode([['path' => $s->file_path, 'name' => $s->file_name ?? basename($s->file_path), 'size' => null]])]);
        });
    }

    public function down(): void
    {
        Schema::table('assignments', fn (Blueprint $t) => $t->dropColumn('files'));
        Schema::table('submissions', fn (Blueprint $t) => $t->dropColumn('files'));
    }
};
