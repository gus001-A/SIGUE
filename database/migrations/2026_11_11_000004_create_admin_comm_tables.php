<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SIGUE · Finanzas (becas, cargos/adeudos, pagos) y comunicación
 * (notificaciones, mensajes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scholarships', function (Blueprint $t) {
            $t->id();
            $t->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $t->foreignId('student_id')->nullable()->constrained()->nullOnDelete();   // beneficiario
            $t->string('code', 30);
            $t->string('name');
            $t->string('type', 30)->default('academica');     // academica | deportiva | socioeconomica | convenio | otra
            $t->decimal('percentage', 5, 2)->nullable();
            $t->decimal('amount', 10, 2)->nullable();
            $t->date('starts_on')->nullable();
            $t->date('ends_on')->nullable();
            $t->text('conditions')->nullable();
            $t->string('status', 20)->default('activa');      // activa | suspendida | vencida
            $t->timestamps();
        });

        // Adeudos / cargos: colegiaturas, inscripciones, cooperaciones, servicios.
        Schema::create('charges', function (Blueprint $t) {
            $t->id();
            $t->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $t->foreignId('student_id')->constrained()->cascadeOnDelete();
            $t->foreignId('school_year_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('scholarship_id')->nullable()->constrained()->nullOnDelete();
            $t->string('type', 20)->default('colegiatura');   // colegiatura | inscripcion | cooperacion | servicio | otro
            $t->string('concept');
            $t->decimal('amount', 10, 2);
            $t->decimal('discount', 10, 2)->default(0);
            $t->date('due_on')->nullable();
            $t->string('status', 20)->default('pendiente');   // pendiente | parcial | pagado | cancelado
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $t->foreignId('student_id')->constrained()->cascadeOnDelete();
            $t->foreignId('charge_id')->nullable()->constrained()->nullOnDelete();
            $t->string('folio', 30);
            $t->decimal('amount', 10, 2);
            $t->string('method', 20)->default('efectivo');    // efectivo | transferencia | tarjeta | deposito
            $t->string('reference')->nullable();
            $t->dateTime('paid_at');
            $t->string('status', 20)->default('aplicado');    // aplicado | cancelado
            $t->text('notes')->nullable();
            $t->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['institution_id', 'folio']);
        });

        Schema::create('notifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();     // destinatario
            $t->string('type', 40);                         // falta | retardo | tarea | calificacion | incidencia | reunion | evento | adeudo | comunicado | mensaje
            $t->string('title');
            $t->text('message');
            $t->string('url')->nullable();
            $t->string('reference_type')->nullable();
            $t->unsignedBigInteger('reference_id')->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'read_at']);
        });

        Schema::create('messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('student_id')->nullable()->constrained()->nullOnDelete();   // contexto
            $t->foreignId('parent_id')->nullable()->constrained('messages')->cascadeOnDelete();
            $t->string('kind', 20)->default('mensaje');       // mensaje | reunion | comunicado
            $t->string('subject');
            $t->text('body');
            $t->dateTime('meeting_at')->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
            $t->index(['recipient_id', 'read_at']);
        });
    }

    public function down(): void
    {
        foreach (['messages', 'notifications', 'payments', 'charges', 'scholarships'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
