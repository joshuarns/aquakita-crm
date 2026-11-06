<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Empresas (tenants). Cada una aísla sus datos vía company_id.
     */
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('brand_color', 20)->default('#0e7490');
            $table->string('logo_path')->nullable();          // ruta del logo en storage
            $table->string('timezone')->default('America/Mexico_City');
            $table->string('locale', 5)->default('es');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
