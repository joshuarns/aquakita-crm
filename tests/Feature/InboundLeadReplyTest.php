<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadMessage;
use App\Support\InboundLeadReply;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InboundLeadReplyTest extends TestCase
{
    use RefreshDatabase;

    private function company(): Company
    {
        return Company::create([
            'name' => 'Empresa X',
            'slug' => 'empresa-x-'.uniqid(),
            'brand_color' => '#0e7490',
            'timezone' => 'America/Mexico_City',
            'locale' => 'es',
            'active' => true,
        ]);
    }

    public function test_matches_by_reply_token(): void
    {
        $company = $this->company();
        $lead = Lead::factory()->create([
            'company_id' => $company->id,
            'reply_token' => 'abcd1234abcd1234',
            'email' => 'otro@correo.com',
        ]);

        $message = app(InboundLeadReply::class)->record(
            ['leads+abcd1234abcd1234@proyectopublicitario.com.mx'],
            'quien-sea@example.com',
            'Re: tu mensaje',
            'Sí, me interesa.',
            'message-id-1',
        );

        $this->assertNotNull($message);
        $this->assertDatabaseHas('lead_messages', [
            'lead_id' => $lead->id,
            'company_id' => $company->id,
            'direction' => 'in',
            'body' => 'Sí, me interesa.',
        ]);
    }

    public function test_falls_back_to_sender_email(): void
    {
        $company = $this->company();
        $lead = Lead::factory()->create(['company_id' => $company->id, 'email' => 'cliente@correo.com']);

        $message = app(InboundLeadReply::class)->record(
            ['leads@proyectopublicitario.com.mx'], // sin token
            'cliente@correo.com',
            'Re: hola',
            'Aquí estoy',
            'message-id-2',
        );

        $this->assertNotNull($message);
        $this->assertSame($lead->id, $message->lead_id);
    }

    public function test_ignores_duplicate_message_id(): void
    {
        $company = $this->company();
        Lead::factory()->create(['company_id' => $company->id, 'reply_token' => 'tttttttttttttttt', 'email' => 'a@b.com']);

        $inbound = app(InboundLeadReply::class);
        $first = $inbound->record(['leads+tttttttttttttttt@dominio.com'], 'a@b.com', 'x', 'uno', 'dup-1');
        $second = $inbound->record(['leads+tttttttttttttttt@dominio.com'], 'a@b.com', 'x', 'dos', 'dup-1');

        $this->assertNotNull($first);
        $this->assertNull($second);
        $this->assertSame(1, LeadMessage::withoutGlobalScopes()->count());
    }

    public function test_returns_null_when_no_lead_matches(): void
    {
        $message = app(InboundLeadReply::class)->record(
            ['leads+zzzzzzzzzzzzzzzz@dominio.com'],
            'desconocido@nadie.com',
            'spam',
            'hola',
            'message-id-3',
        );

        $this->assertNull($message);
    }
}
