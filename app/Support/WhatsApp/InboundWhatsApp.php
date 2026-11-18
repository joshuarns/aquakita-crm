<?php

namespace App\Support\WhatsApp;

use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadMessage;
use App\Models\Status;
use App\Support\Tenancy;

/**
 * Procesa el webhook entrante de WhatsApp Cloud API: enruta cada mensaje a la
 * empresa dueña del número (phone_number_id), lo casa con un lead (por teléfono)
 * o crea uno nuevo, y lo guarda en el hilo.
 */
class InboundWhatsApp
{
    public function __construct(private Tenancy $tenancy) {}

    /**
     * @param  array<string, mixed>  $payload  cuerpo del webhook ya decodificado
     * @return int mensajes guardados
     */
    public function handle(array $payload): int
    {
        $stored = 0;

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];
                $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;

                if (! $phoneNumberId) {
                    continue;
                }

                $company = Company::where('whatsapp_phone_number_id', $phoneNumberId)->first();
                if (! $company) {
                    continue;
                }

                $names = [];
                foreach ($value['contacts'] ?? [] as $contact) {
                    $names[$contact['wa_id'] ?? ''] = $contact['profile']['name'] ?? null;
                }

                foreach ($value['messages'] ?? [] as $message) {
                    if ($this->store($company, $message, $names[$message['from'] ?? ''] ?? null)) {
                        $stored++;
                    }
                }
            }
        }

        return $stored;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    protected function store(Company $company, array $message, ?string $profileName): bool
    {
        $from = (string) ($message['from'] ?? '');
        $waId = $message['id'] ?? null;
        $body = $this->extractBody($message);

        if ($from === '' || $body === null) {
            return false;
        }

        $previous = $this->tenancy->id();
        $this->tenancy->set($company->id);

        try {
            if ($waId && LeadMessage::where('message_id', $waId)->exists()) {
                return false;
            }

            $lead = $this->findOrCreateLead($company, $from, $profileName);

            $lead->messages()->create([
                'company_id' => $company->id,
                'user_id' => null,
                'direction' => 'in',
                'channel' => 'whatsapp',
                'from_name' => $profileName,
                'from_email' => $from, // teléfono del lead
                'to_email' => (string) $company->whatsapp_number,
                'subject' => null,
                'body' => $body,
                'message_id' => $waId,
            ]);

            $lead->recordTimeline('whatsapp_in', 'Mensaje de WhatsApp recibido', [
                'de' => $from,
            ], userId: null);

            return true;
        } finally {
            $this->tenancy->set($previous);
        }
    }

    /**
     * Texto legible del mensaje según su tipo.
     *
     * @param  array<string, mixed>  $message
     */
    protected function extractBody(array $message): ?string
    {
        $type = $message['type'] ?? '';

        return match ($type) {
            'text' => $message['text']['body'] ?? null,
            'button' => $message['button']['text'] ?? null,
            'interactive' => $message['interactive']['button_reply']['title']
                ?? $message['interactive']['list_reply']['title']
                ?? null,
            'image', 'video', 'document', 'audio', 'sticker' => '['.$type.']'
                .(filled($message[$type]['caption'] ?? null) ? ' '.$message[$type]['caption'] : ''),
            'location' => '[ubicación]',
            default => null,
        };
    }

    protected function findOrCreateLead(Company $company, string $fromPhone, ?string $name): Lead
    {
        $digits = WhatsAppGateway::digits($fromPhone);
        $suffix = mb_substr($digits, -10);

        $lead = Lead::where(fn ($q) => $q->where('phone', $digits)->orWhere('phone', 'like', '%'.$suffix))
            ->latest()
            ->first();

        if ($lead) {
            return $lead;
        }

        $parts = $name ? explode(' ', trim($name), 2) : [];

        return Lead::create([
            'first_name' => $parts[0] ?? 'WhatsApp',
            'last_name' => $parts[1] ?? null,
            'phone' => $digits,
            'status_id' => Status::orderBy('order')->value('id'),
        ]);
    }
}
