<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Idioma del formulario/widget (textos fijos: botones, avisos, etc.).
     */
    public function up(): void
    {
        Schema::table('lead_forms', function (Blueprint $table) {
            $table->string('locale', 5)->default('es')->after('display_mode');
        });
    }

    public function down(): void
    {
        Schema::table('lead_forms', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
