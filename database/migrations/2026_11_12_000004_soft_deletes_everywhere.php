<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Todas las eliminaciones del sistema son lógicas (deleted_at), nunca físicas:
 * la información se conserva para auditoría y puede recuperarse.
 */
return new class extends Migration
{
    public const TABLES = [
        'institutions', 'campuses', 'school_years', 'periods', 'grades', 'groups', 'subjects', 'group_subjects', 'schedules',
        'document_types', 'documents', 'enrollments', 'assignments', 'submissions', 'evaluation_criteria', 'grades_records',
        'attendance', 'incidents', 'tutoring_sessions', 'charges', 'payments', 'scholarships', 'messages', 'notifications',
        'student_health', 'roles',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, fn (Blueprint $t) => $t->softDeletes());
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropSoftDeletes());
            }
        }
    }
};
