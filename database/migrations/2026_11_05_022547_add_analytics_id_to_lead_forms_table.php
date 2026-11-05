<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ID de Google Analytics (G-XXXX) o GTM (GTM-XXXX) para cargar en la página
     * del formulario y medir el envío también en el enlace directo.
     */
    public function up(): void
    {
        Schema::table('lead_forms', function (Blueprint $table) {
            $table->string('analytics_id', 40)->nullable()->after('notify_emails');
        });
    }

    public function down(): void
    {
        Schema::table('lead_forms', function (Blueprint $table) {
            $table->dropColumn('analytics_id');
        });
    }
};
