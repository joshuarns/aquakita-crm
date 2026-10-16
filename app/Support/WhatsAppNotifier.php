<?php

namespace App\Support;

use App\Models\Lead;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Envía avisos por WhatsApp vía Twilio. Si no hay credenciales configuradas o
 * el formulario no tiene número, no hace nada (la captura del lead no se afecta).
 */
class WhatsAppNotifier
{
    public function isConfigured(): bool
    {
        return filled(config('services.twilio.sid'))
            && filled(config('services.twilio.token'))
            && filled(config('services.twilio.whatsapp_from'));
    }

    /**
     * Avisa de un lead nuevo al número indicado.
     */
    public function notifyNewLead(Lead $lead, ?string $toNumber): void
    {
        $to = $this->normalize($toNumber);

        if (! $this->isConfigured() || $to === null) {
            return;
        }

        $body = $this->buildMessage($lead);

        try {
            $sid = config('services.twilio.sid');

            $response = Http::asForm()
                ->withBasicAuth($sid, config('services.twilio.token'))
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                    'From' => 'whatsapp:'.$this->normalize(config('services.twilio.whatsapp_from')),
                    'To' => 'whatsapp:'.$to,
                    'Body' => $body,
                ]);

            if ($response->failed()) {
                Log::warning('WhatsApp no enviado', ['status' => $response->status(), 'body' => $response->body()]);
            }
        } catch (\Throwable $e) {
            Log::warning('WhatsApp error: '.$e->getMessage());
        }
    }

    private function buildMessage(Lead $lead): string
    {
        $parts = ["*Nuevo lead* — {$lead->full_name}"];

        if ($lead->phone) {
            $parts[] = "📞 {$lead->phone}";
        }
        if ($lead->email) {
            $parts[] = "✉️ {$lead->email}";
        }
        if ($lead->source) {
            $parts[] = "Origen: {$lead->source->name}";
        }
        if ($lead->description) {
            $parts[] = 'Mensaje: '.Str::limit($lead->description, 160);
        }

        return implode("\n", $parts);
    }

    /**
     * Normaliza a formato E.164 (+<país><número>). Devuelve null si queda vacío.
     */
    private function normalize(?string $number): ?string
    {
        if (blank($number)) {
            return null;
        }

        $digits = preg_replace('/[^0-9+]/', '', $number);
        $digits = ltrim($digits, '+');

        return $digits !== '' ? '+'.$digits : null;
    }
}
