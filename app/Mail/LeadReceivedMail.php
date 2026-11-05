<?php

namespace App\Mail;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso branded al equipo cuando llega un lead desde un formulario web,
 * con los datos del prospecto (estilo de la página web).
 */
class LeadReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Lead $lead) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Nuevo lead desde la web: '.$this->lead->full_name);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.lead-received',
            with: ['lead' => $this->lead->loadMissing(['country', 'projectType', 'source'])],
        );
    }
}
