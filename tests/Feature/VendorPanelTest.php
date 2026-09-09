<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Lead;
use App\Models\Status;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class VendorPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(StatusSeeder::class);
    }

    private function vendor(): User
    {
        $user = User::factory()->create();
        $user->assignRole('vendedor');

        return $user;
    }

    public function test_panel_counts_overdue_follow_ups_for_the_vendor(): void
    {
        $vendor = $this->vendor();
        $lead = Lead::factory()->create(['vendor_id' => $vendor->id]);

        Activity::factory()->overdue()->create(['lead_id' => $lead->id, 'performed_by' => $vendor->id]);
        Activity::factory()->overdue()->create(['lead_id' => $lead->id, 'performed_by' => $vendor->id]);
        Activity::factory()->dueToday()->create(['lead_id' => $lead->id, 'performed_by' => $vendor->id]);

        $component = Volt::actingAs($vendor)->test('panel');

        $this->assertSame(2, $component->instance()->stats['vencidos']);
    }

    public function test_panel_only_shows_the_vendors_own_activities(): void
    {
        $vendorA = $this->vendor();
        $vendorB = $this->vendor();

        $leadA = Lead::factory()->create(['vendor_id' => $vendorA->id]);
        $leadB = Lead::factory()->create(['vendor_id' => $vendorB->id]);

        Activity::factory()->overdue()->create(['lead_id' => $leadA->id, 'performed_by' => $vendorA->id]);
        Activity::factory()->overdue()->create(['lead_id' => $leadB->id, 'performed_by' => $vendorB->id]);

        $component = Volt::actingAs($vendorA)->test('panel');

        $this->assertSame(1, $component->instance()->stats['vencidos']);
        $component->assertSee($leadA->first_name)->assertDontSee($leadB->first_name);
    }

    public function test_vendor_can_complete_a_follow_up(): void
    {
        $vendor = $this->vendor();
        $lead = Lead::factory()->create(['vendor_id' => $vendor->id]);
        $activity = Activity::factory()->overdue()->create([
            'lead_id' => $lead->id,
            'performed_by' => $vendor->id,
        ]);

        Volt::actingAs($vendor)
            ->test('panel')
            ->call('complete', $activity->id);

        $activity->refresh();

        $this->assertTrue($activity->completed);
        $this->assertNotNull($activity->completed_at);
        $this->assertDatabaseHas('timeline_events', ['lead_id' => $lead->id, 'type' => 'follow_up']);
    }

    public function test_vendor_cannot_complete_another_vendors_follow_up(): void
    {
        $vendorA = $this->vendor();
        $vendorB = $this->vendor();
        $leadB = Lead::factory()->create(['vendor_id' => $vendorB->id]);
        $activity = Activity::factory()->overdue()->create([
            'lead_id' => $leadB->id,
            'performed_by' => $vendorB->id,
        ]);

        Volt::actingAs($vendorA)
            ->test('panel')
            ->call('complete', $activity->id)
            ->assertForbidden();

        $this->assertFalse($activity->fresh()->completed);
    }

    public function test_ventas_cerradas_are_counted(): void
    {
        $vendor = $this->vendor();
        $won = Status::where('is_won', true)->value('id');

        Lead::factory()->create(['vendor_id' => $vendor->id, 'status_id' => $won]);
        Lead::factory()->create(['vendor_id' => $vendor->id]); // no ganado

        $component = Volt::actingAs($vendor)->test('panel');

        $this->assertSame(1, $component->instance()->stats['ventas']);
    }
}
