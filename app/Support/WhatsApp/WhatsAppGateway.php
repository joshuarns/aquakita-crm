<?php

namespace App\Support\WhatsApp;

use App\Models\Company;
use Illuminate\Support\Facades\Http;

/**
 * Envío de mensajes por la WhatsApp Business Cloud API (Graph API de Meta).
 * Compatible con números en modo Coexistencia (la API es la misma).
 */
class WhatsAppGateway
{
    private const GRAPH_VERSION = 'v21.0';

    /**
     * Envía un mensaje de texto. Solo válido dentro de la ventana de 24 h desde
     * el último mensaje del cliente; fuera de ella Meta exige una plantilla.
     *
     * @return string ID del mensaje de WhatsApp (wamid...)
     *
     * @throws \RuntimeException
     */
    public function sendText(Company $company, string $toPhone, string $text): string
    {
        $response = Http::withToken((string) $company->whatsapp_token)
            ->acceptJson()
            ->timeout(15)
            ->post('https://graph.facebook.com/'.self::GRAPH_VERSION.'/'.$company->whatsapp_phone_number_id.'/messages', [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => self::digits($toPhone),
                'type' => 'text',
                'text' => ['preview_url' => false, 'body' => $text],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException((string) ($response->json('error.message') ?: 'Error HTTP '.$response->status()));
        }

        return (string) $response->json('messages.0.id');
    }

    /**
     * Solo dígitos de un teléfono (para comparar y para el campo `to` de la API).
     */
    public static function digits(?string $phone): string
    {
        return preg_replace('/\D+/', '', (string) $phone) ?? '';
    }
}
