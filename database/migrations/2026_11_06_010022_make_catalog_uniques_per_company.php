<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Las columnas únicas de catálogos pasan a ser únicas POR EMPRESA
     * (cada empresa puede tener su propio 'es', su propia plantilla, etc.).
     */
    public function up(): void
    {
        Schema::table('languages', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->unique(['company_id', 'code']);
        });

        Schema::table('email_templates', function (Blueprint $table) {
            $table->dropUnique(['key']);
            $table->unique(['company_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::table('languages', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'code']);
            $table->unique(['code']);
        });

        Schema::table('email_templates', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'key']);
            $table->unique(['key']);
        });
    }
};
