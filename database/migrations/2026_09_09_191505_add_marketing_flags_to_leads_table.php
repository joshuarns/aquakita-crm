<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Banderas de consentimiento para exportación a campañas (§9 reglas de exclusión).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->boolean('no_marketing')->default(false)->after('sale_confirmed'); // "No enviar publicidad"
            $table->boolean('unsubscribed')->default(false)->after('no_marketing');    // solicitó darse de baja
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['no_marketing', 'unsubscribed']);
        });
    }
};
