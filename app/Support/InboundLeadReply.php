<?php

namespace App\Support;

use App\Models\Lead;
use App\Models\LeadMessage;

/**
 * Casa un correo entrante (respuesta de un lead) con el lead correcto y lo guarda
 * en el hilo. Prioridad de emparejamiento:
 *   1) token del Reply-To  ->  leads+TOKEN@buzon  (exacto, sin ambigüedad)
 *   2) correo del remitente (el lead más reciente con ese correo)
 */
class InboundLeadReply
{
    public function __construct(private Tenancy $tenancy) {}

    /**
     * @param  array<int, string>  $toAddresses  destinatarios (To/Cc/Delivered-To)
     */
    public function record(
        array $toAddresses,
        string $fromEmail,
        ?string $subject,
        string $body,
        ?string $messageId = null,
        ?string $inReplyTo = null,
        ?int $companyId = null,
    ): ?LeadMessage {
        $lead = $this->matchLead($toAddresses, $fromEmail, $companyId);

        if (! $lead) {
            return null;
        }

        // Dentro del tenancy del lead para que company_id se llene solo (CLI no lo fija).
        $previous = $this->tenancy->id();
        $this->tenancy->set($lead->company_id);

        try {
            if ($messageId && LeadMessage::where('message_id', $messageId)->exists()) {
                return null; // ya guardado (evita duplicados al re-leer el buzón)
            }

            $message = LeadMessage::create([
                'lead_id' => $lead->id,
                'user_id' => null,
                'direction' => 'in',
                'from_name' => null,
                'from_email' => $fromEmail,
                'to_email' => $toAddresses[0] ?? '',
                'subject' => $subject,
                'body' => $body,
                'message_id' => $messageId,
                'in_reply_to' => $inReplyTo,
            ]);

            $lead->recordTimeline('email_in', 'Respuesta del lead recibida', [
                'de' => $fromEmail,
                'asunto' => $subject,
            ], userId: null);

            return $message;
        } finally {
            $this->tenancy->set($previous);
        }
    }

    /**
     * @param  array<int, string>  $toAddresses
     */
    protected function matchLead(array $toAddresses, string $fromEmail, ?int $companyId = null): ?Lead
    {
        // 1) Token del Reply-To (único globalmente).
        foreach ($toAddresses as $address) {
            if (preg_match('/\+([a-z0-9]{16})@/i', (string) $address, $m)) {
                $lead = Lead::where('reply_token', mb_strtolower($m[1]))->first();
                if ($lead) {
                    return $lead;
                }
            }
        }

        // 2) Respaldo: correo del remitente, acotado a la empresa del buzón si se conoce.
        if ($fromEmail === '') {
            return null;
        }

        return Lead::where('email', $fromEmail)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->latest()
            ->first();
    }
}
