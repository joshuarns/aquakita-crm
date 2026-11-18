<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadMessage;
use App\Models\Status;
use App\Support\WhatsApp\InboundWhatsApp;
use App\Support\WhatsApp\WhatsAppGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function company(array $overrides = []): Company
    {
        return Company::create(array_merge([
            'name' => 'Empresa WA',
            'slug' => 'empresa-wa-'.uniqid(),
            'brand_color' => '#0e7490',
            'timezone' => 'America/Mexico_City',
            'locale' => 'es',
            'active' => true,
            'whatsapp_enabled' => true,
            'whatsapp_number' => '+525500000000',
            'whatsapp_phone_number_id' => 'PNID123',
            'whatsapp_token' => 'token-secreto',
        ], $overrides));
    }

    private function inboundPayload(string $phoneNumberId, string $from, string $text, string $waId = 'wamid.ABC'): array
    {
        return [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'metadata' => ['phone_number_id' => $phoneNumberId],
                        'contacts' => [['wa_id' => $from, 'profile' => ['name' => 'Juan Pérez']]],
                        'messages' => [[
                            'from' => $from,
                            'id' => $waId,
                            'type' => 'text',
                            'text' => ['body' => $text],
                        ]],
                    ],
                ]],
            ]],
        ];
    }

    public function test_inbound_message_matches_existing_lead_by_phone(): void
    {
        $company = $this->company();
        $lead = Lead::factory()->create(['company_id' => $company->id, 'phone' => '5588421878']);

        $count = app(InboundWhatsApp::class)->handle(
            $this->inboundPayload('PNID123', '5215588421878', 'Hola, me interesa')
        );

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('lead_messages', [
            'lead_id' => $lead->id,
            'company_id' => $company->id,
            'channel' => 'whatsapp',
            'direction' => 'in',
            'body' => 'Hola, me interesa',
        ]);
    }

    public function test_inbound_creates_new_lead_when_none_matches(): void
    {
        $company = $this->company();
        Status::create(['company_id' => $company->id, 'order' => 1, 'name' => 'Nuevo', 'is_final' => false, 'is_won' => false, 'active' => true]);

        app(InboundWhatsApp::class)->handle(
            $this->inboundPayload('PNID123', '5219991112233', 'Quiero información')
        );

        $this->assertDatabaseHas('leads', [
            'company_id' => $company->id,
            'first_name' => 'Juan',
            'last_name' => 'Pérez',
            'phone' => '5219991112233',
        ]);
    }

    public function test_inbound_ignores_unknown_phone_number_id(): void
    {
        $this->company();

        $count = app(InboundWhatsApp::class)->handle(
            $this->inboundPayload('OTRO-PNID', '5215588421878', 'hola')
        );

        $this->assertSame(0, $count);
    }

    public function test_inbound_dedupes_by_message_id(): void
    {
        $company = $this->company();
        Lead::factory()->create(['company_id' => $company->id, 'phone' => '5588421878']);

        $inbound = app(InboundWhatsApp::class);
        $inbound->handle($this->inboundPayload('PNID123', '5215588421878', 'uno', 'wamid.DUP'));
        $inbound->handle($this->inboundPayload('PNID123', '5215588421878', 'dos', 'wamid.DUP'));

        $this->assertSame(1, LeadMessage::withoutGlobalScopes()->where('channel', 'whatsapp')->count());
    }

    public function test_gateway_sends_text_message(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.OUT']]])]);
        $company = $this->company();

        $id = app(WhatsAppGateway::class)->sendText($company, '+52 55 1234 5678', 'Hola 👋');

        $this->assertSame('wamid.OUT', $id);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'PNID123/messages')
                && $request['to'] === '525512345678'
                && $request['text']['body'] === 'Hola 👋';
        });
    }

    public function test_webhook_verification(): void
    {
        $this->company(['whatsapp_verify_token' => 'VT-123']);

        $query = http_build_query(['hub.mode' => 'subscribe', 'hub.verify_token' => 'VT-123', 'hub.challenge' => '98765']);
        $this->get('/webhooks/whatsapp?'.$query)->assertOk()->assertSee('98765');

        $bad = http_build_query(['hub.mode' => 'subscribe', 'hub.verify_token' => 'WRONG', 'hub.challenge' => '98765']);
        $this->get('/webhooks/whatsapp?'.$bad)->assertForbidden();
    }

    public function test_webhook_receives_and_stores(): void
    {
        $company = $this->company();
        $lead = Lead::factory()->create(['company_id' => $company->id, 'phone' => '5588421878']);

        $this->postJson('/webhooks/whatsapp', $this->inboundPayload('PNID123', '5215588421878', 'Vía webhook'))
            ->assertOk();

        $this->assertDatabaseHas('lead_messages', [
            'lead_id' => $lead->id,
            'channel' => 'whatsapp',
            'body' => 'Vía webhook',
        ]);
    }
}
