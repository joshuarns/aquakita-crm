<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notificaciones al vendedor (§4.3).
 * Campanita interna + correo. Se registra envío, lectura y, cuando el vendedor
 * entra a la ficha del lead, la apertura (§4.3 último punto, §5 "Notificación abierta").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('users')->cascadeOnDelete();

            // Tipo (§4.3): nuevo lead, reasignación, seguimiento próximo, seguimiento vencido
            $table->string('type');
            $table->string('message')->nullable();

            $table->timestamp('sent_at')->nullable();     // envío interno/correo
            $table->timestamp('emailed_at')->nullable();  // correo enviado
            $table->timestamp('read_at')->nullable();     // leída (campanita)
            $table->timestamp('opened_at')->nullable();   // abrió la ficha del lead

            $table->timestamps();

            $table->index(['vendor_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_notifications');
    }
};
