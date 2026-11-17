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
 * Respuesta por correo que un vendedor/admin envía a un lead desde la ficha.
 * Lleva un Reply-To tokenizado para casar la respuesta entrante con el lead.
 */
class LeadReplyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $bodyText,
        public Address $fromAddress,
        public ?Address $replyToAddress = null,
        public ?Company $company = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->fromAddress,
            replyTo: $this->replyToAddress ? [$this->replyToAddress] : [],
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.lead-reply',
            with: [
                'body' => $this->bodyText,
                'company' => $this->company,
            ],
        );
    }
}
