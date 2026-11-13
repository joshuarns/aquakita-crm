<?php

namespace App\Mail;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
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
        return new Envelope(
            from: $this->companyFrom(),
            subject: $this->subjectLine,
        );
    }

    /**
     * Remitente con el nombre (y correo opcional) de la empresa.
     */
    private function companyFrom(): ?Address
    {
        if (! $this->company) {
            return null;
        }

        return new Address($this->company->mailFromAddress(), $this->company->mailFromName());
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
