<?php

namespace Tests\Feature;

use App\Models\LeadForm;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class LeadFormEditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('administrador');

        return $user;
    }

    public function test_admin_can_customize_submit_button_label(): void
    {
        $form = LeadForm::factory()->create();

        Volt::actingAs($this->admin())
            ->test('catalogs.form-edit', ['form' => $form])
            ->set('submit_label', 'Solicitar cotización')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('lead_forms', [
            'id' => $form->id,
            'submit_label' => 'Solicitar cotización',
        ]);
    }

    public function test_blank_submit_label_is_stored_as_null(): void
    {
        $form = LeadForm::factory()->create(['submit_label' => 'Texto previo']);

        Volt::actingAs($this->admin())
            ->test('catalogs.form-edit', ['form' => $form])
            ->set('submit_label', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('lead_forms', [
            'id' => $form->id,
            'submit_label' => null,
        ]);
    }

    public function test_admin_can_customize_confirmation_email(): void
    {
        $form = LeadForm::factory()->create();

        Volt::actingAs($this->admin())
            ->test('catalogs.form-edit', ['form' => $form])
            ->set('confirmation_subject', 'Gracias {{lead}}')
            ->set('confirmation_body', 'Te contactaremos pronto.')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('lead_forms', [
            'id' => $form->id,
            'confirmation_subject' => 'Gracias {{lead}}',
            'confirmation_body' => 'Te contactaremos pronto.',
        ]);
    }

    public function test_admin_can_customize_form_style(): void
    {
        $form = LeadForm::factory()->create();

        Volt::actingAs($this->admin())
            ->test('catalogs.form-edit', ['form' => $form])
            ->set('bg_color', '#101828')
            ->set('text_color', '#ffffff')
            ->set('border_color', '#334155')
            ->set('border_radius', 24)
            ->set('padding', 40)
            ->set('field_radius', 4)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('lead_forms', [
            'id' => $form->id,
            'bg_color' => '#101828',
            'text_color' => '#ffffff',
            'border_color' => '#334155',
            'border_radius' => 24,
            'padding' => 40,
            'field_radius' => 4,
        ]);

        $style = $form->fresh()->style();
        $this->assertSame('#101828', $style['bg']);
        $this->assertSame(24, $style['radius']);
    }

    public function test_style_rejects_out_of_range_values(): void
    {
        $form = LeadForm::factory()->create();

        Volt::actingAs($this->admin())
            ->test('catalogs.form-edit', ['form' => $form])
            ->set('border_radius', 999)
            ->call('save')
            ->assertHasErrors('border_radius');
    }

    public function test_admin_can_set_whatsapp_button(): void
    {
        $form = LeadForm::factory()->create();

        Volt::actingAs($this->admin())
            ->test('catalogs.form-edit', ['form' => $form])
            ->set('whatsapp_number', '+52 55 9999 0000')
            ->set('whatsapp_label', 'Enviar mis datos por WhatsApp')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('lead_forms', [
            'id' => $form->id,
            'whatsapp_number' => '+52 55 9999 0000',
            'whatsapp_label' => 'Enviar mis datos por WhatsApp',
        ]);
    }
}
