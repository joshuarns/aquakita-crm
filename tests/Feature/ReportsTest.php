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

class ReportsTest extends TestCase
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

    public function test_conversion_to_sale_is_computed(): void
    {
        $wonId = Status::where('is_won', true)->value('id');
        Lead::factory()->count(3)->create();
        Lead::factory()->create(['status_id' => $wonId]);

        $stats = Volt::actingAs($this->userWithRole('supervisor'))
            ->test('reports')
            ->instance()->stats;

        $this->assertSame(4, $stats['recibidos']);
        $this->assertSame(1, $stats['ventas']);
        $this->assertSame(25.0, $stats['conv_venta']);
    }

    public function test_unassigned_count_is_computed(): void
    {
        $vendor = $this->userWithRole('vendedor');
        Lead::factory()->count(2)->create(['vendor_id' => null]);
        Lead::factory()->create(['vendor_id' => $vendor->id]);

        $stats = Volt::actingAs($this->userWithRole('supervisor'))
            ->test('reports')
            ->instance()->stats;

        $this->assertSame(2, $stats['sin_asignar']);
    }

    public function test_average_opening_time_is_computed(): void
    {
        Lead::factory()->create([
            'assigned_at' => now(),
            'first_opened_at' => now()->addMinutes(30),
        ]);

        $stats = Volt::actingAs($this->userWithRole('supervisor'))
            ->test('reports')
            ->instance()->stats;

        $this->assertSame(30.0, $stats['tiempo_apertura']);
    }

    public function test_quoted_conversion_uses_status_history(): void
    {
        $cotizadoId = Status::where('order', 7)->value('id');
        $lead = Lead::factory()->create(['first_contact_at' => now()]);
        $lead->statusHistories()->create([
            'to_status_id' => $cotizadoId,
            'user_id' => $this->userWithRole('administrador')->id,
        ]);

        $stats = Volt::actingAs($this->userWithRole('supervisor'))
            ->test('reports')
            ->instance()->stats;

        $this->assertSame(1, $stats['cotizados']);
        $this->assertSame(1, $stats['atendidos']);
        $this->assertSame(100.0, $stats['conv_cotizacion']);
    }

    public function test_supervisor_can_view_reports(): void
    {
        $this->actingAs($this->userWithRole('supervisor'))
            ->get(route('reports'))
            ->assertOk();
    }

    public function test_capturista_and_vendor_cannot_view_reports(): void
    {
        $this->actingAs($this->userWithRole('capturista'))
            ->get(route('reports'))
            ->assertForbidden();

        $this->actingAs($this->userWithRole('vendedor'))
            ->get(route('reports'))
            ->assertForbidden();
    }
}
