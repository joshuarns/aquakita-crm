<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Campos para el modo de widget flotante y el aviso por WhatsApp.
     */
    public function up(): void
    {
        Schema::table('lead_forms', function (Blueprint $table) {
            $table->string('display_mode')->default('inline')->after('active'); // inline | floating
            $table->string('launcher_label')->nullable()->after('display_mode');
            $table->string('launcher_icon', 16)->nullable()->after('launcher_label');
            $table->string('launcher_position', 10)->default('right')->after('launcher_icon'); // right | left
            $table->string('whatsapp_number', 25)->nullable()->after('launcher_position');
        });
    }

    public function down(): void
    {
        Schema::table('lead_forms', function (Blueprint $table) {
            $table->dropColumn(['display_mode', 'launcher_label', 'launcher_icon', 'launcher_position', 'whatsapp_number']);
        });
    }
};
