<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Línea de tiempo de la ficha (§5 "Eventos de la línea de tiempo").
 * Registro unificado e inmutable de todo lo que le pasa a un lead:
 * capturado, asignado, notificación abierta, contacto, seguimiento,
 * cambio de estatus, cierre o descarte. data guarda el detalle por evento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timeline_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // captured, assigned, notification_opened, contact, follow_up, status_change, closed, discarded
            $table->string('description')->nullable();
            $table->json('data')->nullable(); // información conservada por tipo de evento (§5)
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['lead_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timeline_events');
    }
};
