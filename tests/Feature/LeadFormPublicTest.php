<?php

namespace Tests\Feature;

use App\Mail\LeadConfirmationMail;
use App\Mail\LeadReceivedMail;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadForm;
use App\Models\Source;
use App\Models\Status;
use App\Models\User;
use App\Support\LeadNotifier;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
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

    public function test_custom_submit_label_overrides_default(): void
    {
        $form = LeadForm::factory()->create(['submit_label' => 'Quiero información']);

        $this->get(route('public.forms.show', $form->token))
            ->assertOk()
            ->assertSee('Quiero información')
            ->assertDontSee('>Enviar<', escape: false);
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

    public function test_new_web_lead_notifies_admins_and_supervisors(): void
    {
        Mail::fake();
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create(['active' => true]);
        $admin->assignRole('administrador');
        $vendedor = User::factory()->create(['active' => true]);
        $vendedor->assignRole('vendedor');

        $form = LeadForm::factory()->create();

        $this->post(route('public.forms.submit', $form->token), [
            '_nonce' => $this->freshNonce(),
            'first_name' => 'Ana',
            'email' => 'ana@example.com',
        ])->assertOk();

        $lead = Lead::first();

        // El administrador recibe campanita + correo; el vendedor NO (no ve la bandeja general).
        $this->assertDatabaseHas('lead_notifications', [
            'lead_id' => $lead->id, 'vendor_id' => $admin->id, 'type' => 'lead_recibido',
        ]);
        $this->assertDatabaseMissing('lead_notifications', ['vendor_id' => $vendedor->id]);
        Mail::assertSent(LeadReceivedMail::class, fn ($mail) => $mail->hasTo($admin->email));
    }

    public function test_widget_script_is_served(): void
    {
        $widget = LeadForm::factory()->create(['display_mode' => 'floating', 'launcher_label' => '¿Hablamos?']);

        $this->get(route('public.forms.widget', $widget->token))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/javascript; charset=utf-8')
            ->assertSee($widget->token);
    }

    public function test_whatsapp_handoff_link_shown_on_success(): void
    {
        $widget = LeadForm::factory()->create([
            'display_mode' => 'floating',
            'whatsapp_number' => '+52 55 1234 5678',
        ]);

        $this->post(route('public.forms.submit', $widget->token), [
            '_nonce' => $this->freshNonce(),
            'first_name' => 'Ana',
            'email' => 'ana@example.com',
        ])
            ->assertOk()
            ->assertSee('Continuar por WhatsApp')
            ->assertSee('https://wa.me/525512345678', escape: false);
    }

    public function test_whatsapp_button_with_custom_label_on_embedded_form(): void
    {
        $form = LeadForm::factory()->create([
            'whatsapp_number' => '+52 55 1234 5678',
            'whatsapp_label' => 'Enviar mis datos por WhatsApp',
        ]);

        $this->post(route('public.forms.submit', $form->token), [
            '_nonce' => $this->freshNonce(),
            'first_name' => 'Ana',
            'email' => 'ana@example.com',
        ])
            ->assertOk()
            ->assertSee('Enviar mis datos por WhatsApp')
            ->assertSee('https://wa.me/525512345678', escape: false);
    }

    public function test_no_whatsapp_link_without_number(): void
    {
        $form = LeadForm::factory()->create(['whatsapp_number' => null]);

        $this->post(route('public.forms.submit', $form->token), [
            '_nonce' => $this->freshNonce(),
            'first_name' => 'Ana',
            'email' => 'ana@example.com',
        ])
            ->assertOk()
            ->assertDontSee('wa.me');
    }

    public function test_free_text_catalog_fields_resolve_to_ids(): void
    {
        $form = LeadForm::factory()->create([
            'fields' => [
                ['key' => 'first_name', 'enabled' => true, 'required' => true, 'label' => 'Nombre'],
                ['key' => 'project_type_id', 'enabled' => true, 'required' => false, 'label' => 'Tipo de proyecto'],
                ['key' => 'country_id', 'enabled' => true, 'required' => false, 'label' => 'País'],
            ],
        ]);

        $this->post(route('public.forms.submit', $form->token), [
            '_nonce' => $this->freshNonce(),
            'first_name' => 'Ana',
            'project_type_id' => 'Riego industrial',
            'country_id' => 'Guatemala',
        ])->assertOk();

        $lead = Lead::first();
        $this->assertSame('Riego industrial', $lead->projectType->name);
        $this->assertSame('Guatemala', $lead->country->name);
    }

    public function test_client_receives_confirmation_email(): void
    {
        Mail::fake();
        $form = LeadForm::factory()->create();

        $this->post(route('public.forms.submit', $form->token), [
            '_nonce' => $this->freshNonce(),
            'first_name' => 'Ana',
            'email' => 'ana@example.com',
        ])->assertOk();

        Mail::assertSent(LeadConfirmationMail::class, fn ($mail) => $mail->hasTo('ana@example.com'));
    }

    public function test_form_custom_confirmation_message_is_used(): void
    {
        Mail::fake();
        $form = LeadForm::factory()->create([
            'confirmation_subject' => 'Recibido, {{lead}}',
            'confirmation_body' => 'Hola {{lead}}, gracias por escribir. Pronto te contactamos.',
        ]);

        $this->post(route('public.forms.submit', $form->token), [
            '_nonce' => $this->freshNonce(),
            'first_name' => 'Ana',
            'email' => 'ana@example.com',
        ])->assertOk();

        Mail::assertSent(LeadConfirmationMail::class, function ($mail) {
            return $mail->hasTo('ana@example.com')
                && str_contains($mail->subjectLine, 'Recibido, Ana')
                && str_contains($mail->body, 'gracias por escribir');
        });
    }

    public function test_confirmation_email_sends_when_lead_has_client_company(): void
    {
        // Regresión: la columna string `company` (empresa del cliente) ocultaba la
        // relación company() del tenant y rompía el envío del correo de confirmación.
        Mail::fake();

        $company = Company::create([
            'name' => 'Mi Empresa',
            'slug' => 'mi-empresa-test',
            'brand_color' => '#0e7490',
            'timezone' => 'America/Mexico_City',
            'locale' => 'es',
            'active' => true,
        ]);

        $lead = Lead::factory()->create([
            'company' => 'Addabra',
            'company_id' => $company->id,
            'email' => 'cliente@example.com',
        ]);

        app(LeadNotifier::class)->notifyClient($lead);

        Mail::assertSent(LeadConfirmationMail::class, fn ($mail) => $mail->hasTo('cliente@example.com'));
    }

    public function test_no_confirmation_email_without_client_email(): void
    {
        Mail::fake();
        $form = LeadForm::factory()->create([
            'fields' => [['key' => 'first_name', 'enabled' => true, 'required' => true, 'label' => 'Nombre']],
        ]);

        $this->post(route('public.forms.submit', $form->token), [
            '_nonce' => $this->freshNonce(),
            'first_name' => 'Ana',
        ])->assertOk();

        Mail::assertNothingSent();
    }

    public function test_form_notify_emails_override_recipients(): void
    {
        Mail::fake();
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create(['active' => true]);
        $admin->assignRole('administrador');

        $form = LeadForm::factory()->create([
            'notify_emails' => ['ventas@aquakita.com', 'gerente@aquakita.com'],
        ]);

        $this->post(route('public.forms.submit', $form->token), [
            '_nonce' => $this->freshNonce(),
            'first_name' => 'Ana',
            'email' => 'ana@example.com',
        ])->assertOk();

        Mail::assertSent(LeadReceivedMail::class, fn ($m) => $m->hasTo('ventas@aquakita.com'));
        Mail::assertSent(LeadReceivedMail::class, fn ($m) => $m->hasTo('gerente@aquakita.com'));
        // El admin NO recibe el correo (el formulario lo sobreescribe), pero sí la campanita.
        Mail::assertNotSent(LeadReceivedMail::class, fn ($m) => $m->hasTo($admin->email));
        $this->assertDatabaseHas('lead_notifications', ['vendor_id' => $admin->id, 'type' => 'lead_recibido']);
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
