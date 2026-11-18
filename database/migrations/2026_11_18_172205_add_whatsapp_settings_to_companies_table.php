<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('whatsapp_enabled')->default(false)->after('imap_validate_cert');
            $table->string('whatsapp_number')->nullable()->after('whatsapp_enabled');        // visible, p. ej. +52...
            $table->string('whatsapp_phone_number_id')->nullable()->after('whatsapp_number'); // Phone Number ID de Meta
            $table->string('whatsapp_business_id')->nullable()->after('whatsapp_phone_number_id');
            $table->text('whatsapp_token')->nullable()->after('whatsapp_business_id');        // cifrado (cast encrypted)
            $table->string('whatsapp_verify_token')->nullable()->after('whatsapp_token');     // para verificar el webhook
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'whatsapp_enabled', 'whatsapp_number', 'whatsapp_phone_number_id',
                'whatsapp_business_id', 'whatsapp_token', 'whatsapp_verify_token',
            ]);
        });
    }
};
