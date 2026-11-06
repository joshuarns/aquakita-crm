<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Logo de la empresa en base64 (se sirve por ruta y se embebe en correos).
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->longText('logo')->nullable()->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('logo');
        });
    }
};
