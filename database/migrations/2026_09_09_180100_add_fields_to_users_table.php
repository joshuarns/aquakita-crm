<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos extra de usuario (§2, §10).
 * El rol se maneja con spatie/laravel-permission; aquí solo estado y zona horaria.
 * Los usuarios se activan/desactivan sin borrar su historial (§2 Reglas de acceso).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('active')->default(true)->after('email');
            $table->string('timezone')->default('America/Mexico_City')->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['active', 'timezone']);
        });
    }
};
