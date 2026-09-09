<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de cambios de estatus (§5 evento "Cambio de estatus", §7).
 * Registro inmutable: estatus anterior, nuevo y usuario (§10, regla §5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_status_id')->nullable()->constrained('statuses')->nullOnDelete();
            $table->foreignId('to_status_id')->constrained('statuses');
            $table->foreignId('user_id')->constrained('users');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index('lead_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_status_histories');
    }
};
