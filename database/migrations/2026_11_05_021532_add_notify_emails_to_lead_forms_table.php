<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Correos destinatarios del aviso de lead nuevo, por formulario.
     * Vacío = administradores y supervisores (comportamiento por defecto).
     */
    public function up(): void
    {
        Schema::table('lead_forms', function (Blueprint $table) {
            $table->json('notify_emails')->nullable()->after('whatsapp_number');
        });
    }

    public function down(): void
    {
        Schema::table('lead_forms', function (Blueprint $table) {
            $table->dropColumn('notify_emails');
        });
    }
};
