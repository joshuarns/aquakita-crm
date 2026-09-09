<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogos de configuración (§3.2 de la especificación).
 * Todos administrables desde la app, sin tocar el código.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Idiomas
        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique(); // es, en, pt...
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Países
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->nullable(); // ISO
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Ciudades (pertenecen a un país)
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Fuentes de llegada (§3.2 / §3.3)
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Campañas
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Tipos de proyecto
        Schema::create('project_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Estatus comerciales (§7). is_final marca estados terminales.
        Schema::create('statuses', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('order')->default(0);
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_final')->default(false); // venta/descarte/no interesado...
            $table->boolean('is_won')->default(false);   // marca "venta cerrada"
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Motivos de descarte (§3.2)
        Schema::create('discard_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Plantillas de correo (§3.2 / §4.3)
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // nuevo_lead, reasignacion, seguimiento...
            $table->string('subject');
            $table->text('body');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('discard_reasons');
        Schema::dropIfExists('statuses');
        Schema::dropIfExists('project_types');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('sources');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('countries');
        Schema::dropIfExists('languages');
    }
};
