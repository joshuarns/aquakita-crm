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
}
