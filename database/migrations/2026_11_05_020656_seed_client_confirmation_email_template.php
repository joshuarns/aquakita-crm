<?php

use App\Models\EmailTemplate;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Crea la plantilla editable del correo de confirmación al cliente, para que
     * exista también en entornos ya desplegados (los seeders no corren en deploy).
     */
    public function up(): void
    {
        EmailTemplate::firstOrCreate(
            ['key' => 'confirmacion_cliente'],
            [
                'subject' => '¡Gracias por contactarnos! — {{negocio}}',
                'body' => "Hola {{lead}},\n\nRecibimos tu información correctamente. ¡Gracias por tu interés! Uno de nuestros asesores se pondrá en contacto contigo muy pronto para atenderte.\n\nSi tu consulta es urgente, puedes responder directamente a este correo.\n\nSaludos cordiales,\nEquipo {{negocio}}",
            ]
        );
    }

    public function down(): void
    {
        EmailTemplate::where('key', 'confirmacion_cliente')->delete();
    }
};
