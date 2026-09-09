<?php

namespace App\Support;

use App\Mail\LeadNotificationMail;
use App\Models\EmailTemplate;
use App\Models\Lead;
use App\Models\LeadNotification;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Genera las notificaciones al vendedor (§4.3): registro interno para la
 * campanita y aviso por correo, usando las plantillas administrables (§3.2).
 */
class LeadNotifier
{
    /**
     * @param  array<string, string>  $extra  valores extra para la plantilla (ej. fecha)
     */
    public function notify(Lead $lead, User $vendor, string $type, array $extra = []): LeadNotification
    {
        [$subject, $body] = $this->render($type, $lead, $vendor, $extra);

        $notification = LeadNotification::create([
            'lead_id' => $lead->id,
            'vendor_id' => $vendor->id,
            'type' => $type,
            'message' => $subject,
            'sent_at' => now(),
        ]);

        if ($vendor->email) {
            Mail::to($vendor->email)->send(new LeadNotificationMail($subject, $body));
            $notification->update(['emailed_at' => now()]);
        }

        return $notification;
    }

    /**
     * Resuelve asunto y cuerpo desde la plantilla y sustituye los marcadores.
     *
     * @param  array<string, string>  $extra
     * @return array{0: string, 1: string}
     */
    private function render(string $type, Lead $lead, User $vendor, array $extra): array
    {
        $template = EmailTemplate::where('key', $type)->where('active', true)->first();

        $subject = $template?->subject ?? 'Aviso sobre un lead';
        $body = $template?->body ?? 'Tienes una actualización en el lead {{lead}}.';

        $replacements = array_merge([
            'vendedor' => $vendor->name,
            'lead' => $lead->full_name,
        ], $extra);

        foreach ($replacements as $key => $value) {
            $subject = str_replace('{{'.$key.'}}', $value, $subject);
            $body = str_replace('{{'.$key.'}}', $value, $body);
        }

        return [$subject, $body];
    }
}
