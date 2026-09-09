<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leads / prospectos (§3.3 Captura, §5 Ficha).
 * created_at + captured_by = fecha, hora y usuario de captura (automáticos, servidor §10).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();

            // Contacto y empresa (§3.3)
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('company')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            // Ubicación e idioma
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('language_id')->nullable()->constrained()->nullOnDelete();

            // Origen (§3.3)
            $table->foreignId('source_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->string('landing')->nullable();
            $table->string('keyword')->nullable();

            // Proyecto (§3.3)
            $table->foreignId('project_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('budget')->nullable();
            $table->string('deadline')->nullable();
            $table->text('description')->nullable();

            // Estado comercial y asignación
            $table->foreignId('status_id')->constrained();
            $table->foreignId('vendor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('discard_reason_id')->nullable()->constrained()->nullOnDelete();
            $table->text('discard_comment')->nullable();

            // Venta (§10: monto estimado o confirmado)
            $table->decimal('sale_amount', 15, 2)->nullable();
            $table->string('sale_currency', 3)->nullable();
            $table->boolean('sale_confirmed')->default(false);

            // Seguimiento y tiempos clave (§6, §8 KPIs)
            $table->timestamp('next_follow_up_at')->nullable();
            $table->timestamp('assigned_at')->nullable();      // para tiempo de apertura/primer contacto
            $table->timestamp('first_opened_at')->nullable();  // cuándo abrió la ficha
            $table->timestamp('first_contact_at')->nullable(); // primer contacto real

            // Auditoría de captura (§10)
            $table->foreignId('captured_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('status_id');
            $table->index('vendor_id');
            $table->index('email');
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
