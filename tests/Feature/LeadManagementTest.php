<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Status;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class LeadManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(StatusSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_capturista_can_capture_a_lead(): void
    {
        $capturista = $this->userWithRole('capturista');

        Volt::actingAs($capturista)
            ->test('leads.create')
            ->set('first_name', 'Ana')
            ->set('last_name', 'López')
            ->set('company', 'Acme')
            ->set('email', 'ana@acme.test')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('leads.index'));

        $lead = Lead::first();

        $this->assertNotNull($lead);
        $this->assertSame('Ana', $lead->first_name);
        $this->assertSame($capturista->id, $lead->captured_by);
        $this->assertSame(1, $lead->status->order); // "Nuevo"
        $this->assertDatabaseHas('timeline_events', [
            'lead_id' => $lead->id,
            'type' => 'captured',
        ]);
    }

    public function test_capture_requires_first_name(): void
    {
        $capturista = $this->userWithRole('capturista');

        Volt::actingAs($capturista)
            ->test('leads.create')
            ->set('first_name', '')
            ->call('save')
            ->assertHasErrors(['first_name' => 'required']);

        $this->assertDatabaseCount('leads', 0);
    }

    public function test_duplicate_warning_appears_for_matching_email(): void
    {
        $capturista = $this->userWithRole('capturista');
        Lead::factory()->create(['email' => 'repetido@acme.test']);

        Volt::actingAs($capturista)
            ->test('leads.create')
            ->set('email', 'repetido@acme.test')
            ->assertSee('Posibles duplicados');
    }

    public function test_vendedor_cannot_open_capture_screen(): void
    {
        $vendedor = $this->userWithRole('vendedor');

        $this->actingAs($vendedor)
            ->get(route('leads.create'))
            ->assertForbidden();
    }

    public function test_admin_can_assign_lead_to_vendor(): void
    {
        $admin = $this->userWithRole('administrador');
        $vendedor = $this->userWithRole('vendedor');
        $lead = Lead::factory()->create();

        Volt::actingAs($admin)
            ->test('leads.index')
            ->call('assign', $lead->id, $vendedor->id);

        $lead->refresh();

        $this->assertSame($vendedor->id, $lead->vendor_id);
        $this->assertNotNull($lead->assigned_at);
        $this->assertSame(2, $lead->status->order); // avanzó a "Asignado"
        $this->assertDatabaseHas('lead_notifications', [
            'lead_id' => $lead->id,
            'vendor_id' => $vendedor->id,
            'type' => 'nuevo_lead',
        ]);
        $this->assertDatabaseHas('timeline_events', [
            'lead_id' => $lead->id,
            'type' => 'assigned',
        ]);
    }

    public function test_reassigning_keeps_previous_activities(): void
    {
        $admin = $this->userWithRole('administrador');
        $vendorA = $this->userWithRole('vendedor');
        $vendorB = $this->userWithRole('vendedor');

        $lead = Lead::factory()->create([
            'vendor_id' => $vendorA->id,
            'status_id' => Status::where('order', 3)->value('id'),
        ]);
        $lead->activities()->create([
            'type' => 'llamada',
            'comment' => 'Primer intento',
            'performed_by' => $vendorA->id,
        ]);

        Volt::actingAs($admin)
            ->test('leads.index')
            ->call('assign', $lead->id, $vendorB->id);

        $lead->refresh();

        $this->assertSame($vendorB->id, $lead->vendor_id);
        $this->assertSame(1, $lead->activities()->count()); // actividad previa intacta
        $this->assertDatabaseHas('lead_notifications', [
            'lead_id' => $lead->id,
            'type' => 'reasignacion',
        ]);
    }

    public function test_vendor_only_sees_assigned_leads(): void
    {
        $vendorA = $this->userWithRole('vendedor');
        $vendorB = $this->userWithRole('vendedor');

        $mine = Lead::factory()->create(['vendor_id' => $vendorA->id]);
        $other = Lead::factory()->create(['vendor_id' => $vendorB->id]);

        Volt::actingAs($vendorA)
            ->test('leads.index')
            ->assertSee($mine->first_name)
            ->assertDontSee($other->first_name);
    }
}
