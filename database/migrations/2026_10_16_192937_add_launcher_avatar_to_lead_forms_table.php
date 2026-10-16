<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Imagen de avatar (foto del agente) para el botón flotante.
     */
    public function up(): void
    {
        Schema::table('lead_forms', function (Blueprint $table) {
            $table->string('launcher_avatar')->nullable()->after('launcher_icon');
        });
    }

    public function down(): void
    {
        Schema::table('lead_forms', function (Blueprint $table) {
            $table->dropColumn('launcher_avatar');
        });
    }
};
