<?php

namespace Tests\Feature;

use App\Mail\LeadNotificationMail;
use App\Models\Activity;
use App\Models\Lead;
use App\Models\LeadNotification;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(StatusSeeder::class);
        $this->seed(CatalogSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_assigning_a_lead_sends_notification_and_email(): void
    {
        Mail::fake();

        $admin = $this->userWithRole('administrador');
        $vendor = $this->userWithRole('vendedor');
        $lead = Lead::factory()->create();

        Volt::actingAs($admin)
            ->test('leads.index')
            ->call('assign', $lead->id, $vendor->id);

        Mail::assertSent(LeadNotificationMail::class);

        $this->assertDatabaseHas('lead_notifications', [
            'lead_id' => $lead->id,
            'vendor_id' => $vendor->id,
            'type' => 'nuevo_lead',
        ]);
        $this->assertNotNull(LeadNotification::first()->emailed_at);
    }

    public function test_bell_shows_unread_count_for_the_vendor(): void
    {
        $vendor = $this->userWithRole('vendedor');
        $other = $this->userWithRole('vendedor');
        $lead = Lead::factory()->create(['vendor_id' => $vendor->id]);

        LeadNotification::create(['lead_id' => $lead->id, 'vendor_id' => $vendor->id, 'type' => 'nuevo_lead', 'message' => 'x', 'sent_at' => now()]);
        LeadNotification::create(['lead_id' => $lead->id, 'vendor_id' => $other->id, 'type' => 'nuevo_lead', 'message' => 'y', 'sent_at' => now()]);

        $count = Volt::actingAs($vendor)->test('notifications.bell')->instance()->unreadCount;

        $this->assertSame(1, $count);
    }

    public function test_opening_a_notification_marks_it_read_and_redirects(): void
    {
        $vendor = $this->userWithRole('vendedor');
        $lead = Lead::factory()->create(['vendor_id' => $vendor->id]);
        $notification = LeadNotification::create(['lead_id' => $lead->id, 'vendor_id' => $vendor->id, 'type' => 'nuevo_lead', 'message' => 'x', 'sent_at' => now()]);

        Volt::actingAs($vendor)
            ->test('notifications.bell')
            ->call('open', $notification->id)
            ->assertRedirect(route('leads.show', $lead));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_mark_all_read(): void
    {
        $vendor = $this->userWithRole('vendedor');
        $lead = Lead::factory()->create(['vendor_id' => $vendor->id]);
        LeadNotification::create(['lead_id' => $lead->id, 'vendor_id' => $vendor->id, 'type' => 'nuevo_lead', 'message' => 'x', 'sent_at' => now()]);

        Volt::actingAs($vendor)
            ->test('notifications.bell')
            ->call('markAllRead');

        $this->assertSame(0, LeadNotification::where('vendor_id', $vendor->id)->whereNull('read_at')->count());
    }

    public function test_command_notifies_overdue_follow_ups_without_duplicating(): void
    {
        Mail::fake();

        $vendor = $this->userWithRole('vendedor');
        $lead = Lead::factory()->create(['vendor_id' => $vendor->id]);
        Activity::factory()->overdue()->create(['lead_id' => $lead->id, 'performed_by' => $vendor->id]);

        $this->artisan('leads:notify-followups')->assertSuccessful();
        $this->artisan('leads:notify-followups')->assertSuccessful(); // segunda corrida no duplica

        $this->assertSame(1, LeadNotification::where('type', 'seguimiento_vencido')->count());
        Mail::assertSent(LeadNotificationMail::class, 1);
    }
}
