<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadForm;
use App\Models\Source;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class LeadFormPublicTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Status::create([
            'order' => 1,
            'name' => 'Nuevo',
            'description' => 'Estatus inicial',
            'is_final' => false,
            'is_won' => false,
            'active' => true,
        ]);
    }

    private function freshNonce(): string
    {
        return Crypt::encryptString((string) now()->timestamp);
    }

    public function test_public_form_page_renders(): void
    {
        $form = LeadForm::factory()->create(['name' => 'Contacto web']);

        $this->get(route('public.forms.show', $form->token))
            ->assertOk()
            ->assertSee('Nombre')
            ->assertSee('Enviar');
    }

    public function test_inactive_form_returns_404(): void
    {
        $form = LeadForm::factory()->inactive()->create();

        $this->get(route('public.forms.show', $form->token))->assertNotFound();
    }

    public function test_valid_submission_creates_lead(): void
    {
        $source = Source::create(['name' => 'Sitio web']);
        $form = LeadForm::factory()->create(['source_id' => $source->id]);

        $response = $this->post(route('public.forms.submit', $form->token), [
            '_nonce' => $this->freshNonce(),
            'first_name' => 'Ana',
            'last_name' => 'García',
            'email' => 'ana@example.com',
            'phone' => '5551234567',
            'description' => 'Quiero cotizar una alberca.',
        ]);

        $response->assertOk()->assertSee('¡Gracias!');

        $lead = Lead::first();
        $this->assertNotNull($lead);
        $this->assertSame('Ana', $lead->first_name);
        $this->assertSame($source->id, $lead->source_id);
        $this->assertSame($form->id, $lead->lead_form_id);
        $this->assertNull($lead->captured_by);
        $this->assertSame(1, $form->fresh()->submissions_count);
        $this->assertDatabaseHas('timeline_events', ['lead_id' => $lead->id, 'type' => 'captured']);
    }

    public function test_honeypot_blocks_submission(): void
    {
        $form = LeadForm::factory()->create();

        $this->post(route('public.forms.submit', $form->token), [
            '_nonce' => $this->freshNonce(),
            'first_name' => 'Bot',
            'email' => 'bot@example.com',
            'website' => 'http://spam.example',
        ])->assertOk();

        $this->assertSame(0, Lead::count());
    }

    public function test_required_field_is_validated(): void
    {
        $form = LeadForm::factory()->create();

        $this->post(route('public.forms.submit', $form->token), [
            '_nonce' => $this->freshNonce(),
            'first_name' => '',
            'email' => 'sinnombre@example.com',
        ])->assertStatus(422);

        $this->assertSame(0, Lead::count());
    }

    public function test_missing_nonce_is_rejected(): void
    {
        $form = LeadForm::factory()->create();

        $this->post(route('public.forms.submit', $form->token), [
            'first_name' => 'Ana',
            'email' => 'ana@example.com',
        ])->assertStatus(422);

        $this->assertSame(0, Lead::count());
    }
}
