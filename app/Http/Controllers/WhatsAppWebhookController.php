<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Support\WhatsApp\InboundWhatsApp;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Webhook público de WhatsApp Cloud API (un solo endpoint para todas las empresas;
 * se enruta por phone_number_id dentro del payload).
 */
class WhatsAppWebhookController extends Controller
{
    /**
     * Verificación del webhook (Meta hace un GET con hub.verify_token).
     */
    public function verify(Request $request): Response
    {
        $token = (string) $request->query('hub_verify_token');
        $challenge = (string) $request->query('hub_challenge');

        if ($request->query('hub_mode') === 'subscribe'
            && filled($token)
            && Company::where('whatsapp_verify_token', $token)->exists()) {
            return response($challenge, 200);
        }

        return response('Forbidden', 403);
    }

    /**
     * Recepción de mensajes (Meta hace POST con el evento).
     */
    public function receive(Request $request, InboundWhatsApp $inbound): Response
    {
        $inbound->handle($request->all());

        // Meta exige 200 rápido; devolvemos OK siempre.
        return response('OK', 200);
    }
}
