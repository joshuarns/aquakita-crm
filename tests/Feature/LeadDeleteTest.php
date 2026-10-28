<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Status;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class LeadDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function makeLead(): Lead
    {
        $status = Status::create([
            'order' => 1, 'name' => 'Nuevo', 'description' => '', 'is_final' => false, 'is_won' => false, 'active' => true,
        ]);

        return Lead::create(['first_name' => 'Ana', 'email' => 'ana@example.com', 'status_id' => $status->id]);
    }

    public function test_administrador_puede_eliminar_un_lead(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create(['active' => true]);
        $admin->assignRole('administrador');
        $lead = $this->makeLead();

        $this->actingAs($admin);
        Volt::test('leads.index')->call('deleteLead', $lead->id);

        $this->assertDatabaseMissing('leads', ['id' => $lead->id]);
    }

    public function test_vendedor_no_puede_eliminar_un_lead(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $vendedor = User::factory()->create(['active' => true]);
        $vendedor->assignRole('vendedor');
        $lead = $this->makeLead();

        $this->actingAs($vendedor);
        Volt::test('leads.index')->call('deleteLead', $lead->id)->assertStatus(403);

        $this->assertDatabaseHas('leads', ['id' => $lead->id]);
    }
}
