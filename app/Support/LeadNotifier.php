<?php

namespace App\Support;

use App\Mail\LeadConfirmationMail;
use App\Mail\LeadNotificationMail;
use App\Mail\LeadReceivedMail;
use App\Models\EmailTemplate;
use App\Models\Lead;
use App\Models\LeadNotification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;

/**
 * Genera las notificaciones al vendedor (§4.3): registro interno para la
 * campanita y aviso por correo, usando las plantillas administrables (§3.2).
 */
class LeadNotifier
{
    /**
     * Envía un correo de confirmación al propio cliente que llenó el formulario.
     * Usa la plantilla editable «confirmacion_cliente» (§3.2); si no existe, usa
     * un texto por defecto. No afecta la captura si el correo falla.
     */
    public function notifyClient(Lead $lead): void
    {
        if (blank($lead->email)) {
            return;
        }

        $negocio = config('app.name', 'Aquakita');
        $template = EmailTemplate::where('key', 'confirmacion_cliente')->where('active', true)->first();

        $subject = $template?->subject ?? '¡Gracias por contactarnos! — {{negocio}}';
        $body = $template?->body ?? "Hola {{lead}},\n\nRecibimos tu información correctamente. ¡Gracias por tu interés! Uno de nuestros asesores se pondrá en contacto contigo muy pronto para atenderte.\n\nSi tu consulta es urgente, puedes responder directamente a este correo.\n\nSaludos cordiales,\nEquipo {{negocio}}";

        foreach (['lead' => $lead->full_name, 'negocio' => $negocio] as $key => $value) {
            $subject = str_replace('{{'.$key.'}}', $value, $subject);
            $body = str_replace('{{'.$key.'}}', $value, $body);
        }

        try {
            Mail::to($lead->email)->send(new LeadConfirmationMail($subject, $body));
        } catch (\Throwable $e) {
            Log::warning('Correo de confirmación al cliente no enviado: '.$e->getMessage());
        }
    }

    /**
     * Avisa a administradores y supervisores que llegó un lead nuevo a la bandeja.
     * Se dispara en la captura manual y en los formularios web.
     */
    public function notifyNewLead(Lead $lead, ?int $excludeUserId = null): void
    {
        if (! Permission::where('name', 'leads.view.all')->exists()) {
            return;
        }

        $recipients = User::permission('leads.view.all')
            ->where('active', true)
            ->when($excludeUserId, fn ($q) => $q->where('id', '!=', $excludeUserId))
            ->get();

        // Campanita interna: siempre a los administradores y supervisores.
        $notifications = [];
        foreach ($recipients as $recipient) {
            $notifications[$recipient->id] = LeadNotification::create([
                'lead_id' => $lead->id,
                'vendor_id' => $recipient->id,
                'type' => 'lead_recibido',
                'message' => 'Nuevo lead: '.$lead->full_name,
                'sent_at' => now(),
            ]);
        }

        // Correo: destinatarios configurados en el formulario, o los admins por defecto.
        $formEmails = collect($lead->leadForm?->notify_emails ?? [])
            ->map(fn ($e) => trim((string) $e))->filter()->unique()->values();

        $emails = $formEmails->isNotEmpty()
            ? $formEmails->all()
            : $recipients->pluck('email')->filter()->unique()->values()->all();

        foreach ($emails as $email) {
            try {
                Mail::to($email)->send(new LeadReceivedMail($lead));
            } catch (\Throwable $e) {
                Log::warning('Aviso de lead nuevo no enviado: '.$e->getMessage());
            }
        }

        // Marca la campanita como "enviada por correo" cuando el admin recibió el aviso.
        foreach ($recipients as $recipient) {
            if ($recipient->email && in_array($recipient->email, $emails, true)) {
                $notifications[$recipient->id]->update(['emailed_at' => now()]);
            }
        }
    }

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
            'destinatario' => $vendor->name,
            'lead' => $lead->full_name,
        ], $extra);

        foreach ($replacements as $key => $value) {
            $subject = str_replace('{{'.$key.'}}', $value, $subject);
            $body = str_replace('{{'.$key.'}}', $value, $body);
        }

        return [$subject, $body];
    }
}
