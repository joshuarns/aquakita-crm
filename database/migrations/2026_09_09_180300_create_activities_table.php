<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Actividades / seguimiento (§6).
 * Cada interacción se registra manualmente con fecha, hora, tipo, comentario,
 * resultado, próxima acción, siguiente seguimiento y usuario que la realizó.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();

            // Tipo de actividad (§6): llamada, whatsapp, correo, videollamada, nota, info, cotizacion
            $table->string('type');
            $table->text('comment');
            $table->string('result')->nullable();

            // Agenda / recordatorio (§6)
            $table->string('next_action')->nullable();
            $table->timestamp('follow_up_at')->nullable();
            $table->boolean('completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->boolean('rescheduled')->default(false);

            // Quién la realizó (§6, §10)
            $table->foreignId('performed_by')->constrained('users');

            $table->timestamps();

            $table->index('lead_id');
            $table->index('follow_up_at');
            $table->index('completed');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
