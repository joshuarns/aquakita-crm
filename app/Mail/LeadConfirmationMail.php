<?php

namespace App\Mail;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Correo de confirmación que recibe el cliente al llenar un formulario web.
 * A diferencia del aviso al vendedor, no incluye enlaces al sistema.
 */
class LeadConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $body,
        public ?Company $company = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.lead-confirmation',
            with: [
                'body' => $this->body,
                'company' => $this->company,
            ],
        );
    }
}
