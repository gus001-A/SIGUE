<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SIGUE · Núcleo: instituciones (multi-tenant), planteles, usuarios, roles,
 * permisos y bitácora de auditoría.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institutions', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('short_name', 60)->nullable();
            $t->string('slug')->unique();
            $t->string('cct', 30)->nullable()->comment('Clave de centro de trabajo');
            $t->string('rfc', 20)->nullable();
            $t->string('email')->nullable();
            $t->string('phone', 20)->nullable();
            $t->string('address')->nullable();
            $t->string('logo_path')->nullable();
            $t->string('primary_color', 9)->nullable();
            $t->string('plan', 30)->default('basico');           // basico | profesional | enterprise
            $t->unsignedInteger('max_students')->nullable();
            $t->unsignedInteger('max_campuses')->nullable();
            $t->json('modules')->nullable();                      // módulos contratados
            $t->date('license_expires_at')->nullable();
            $t->string('status', 20)->default('activa');          // activa | suspendida | prueba
            $t->json('settings')->nullable();
            $t->timestamps();
        });

        Schema::create('campuses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('code', 30)->nullable();
            $t->string('address')->nullable();
            $t->string('phone', 20)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name');
            $t->string('paternal_surname')->nullable();
            $t->string('maternal_surname')->nullable();
            $t->string('email')->unique();
            $t->string('phone', 20)->nullable();
            $t->string('password');
            $t->boolean('is_active')->default(true);
            $t->boolean('must_change_password')->default(false);
            $t->timestamp('last_login_at')->nullable();
            $t->string('last_login_ip', 45)->nullable();
            $t->rememberToken();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('password_reset_tokens', function (Blueprint $t) {
            $t->string('email')->primary();
            $t->string('token');
            $t->timestamp('created_at')->nullable();
        });

        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 40)->unique();
            $t->string('name');
            $t->string('description')->nullable();
            $t->unsignedSmallInteger('level')->default(0);   // para ordenar
            $t->timestamps();
        });

        Schema::create('permissions', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 60)->unique();
            $t->string('name');
            $t->string('module', 40);
            $t->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $t) {
            $t->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $t->foreignId('role_id')->constrained()->cascadeOnDelete();
            $t->primary(['permission_id', 'role_id']);
        });

        Schema::create('role_user', function (Blueprint $t) {
            $t->foreignId('role_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->primary(['role_id', 'user_id']);
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action', 40);                       // created | updated | deleted | login | ...
            $t->string('auditable_type')->nullable();
            $t->unsignedBigInteger('auditable_id')->nullable();
            $t->string('description')->nullable();
            $t->json('old_values')->nullable();
            $t->json('new_values')->nullable();
            $t->string('ip', 45)->nullable();
            $t->string('user_agent')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['auditable_type', 'auditable_id']);
            $t->index(['institution_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'role_user', 'permission_role', 'permissions', 'roles',
                  'password_reset_tokens', 'users', 'campuses', 'institutions'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
