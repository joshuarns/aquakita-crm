<?php

namespace Tests\Feature;

use App\Mail\LeadReplyMail;
use App\Models\DiscardReason;
use App\Models\Lead;
use App\Models\LeadNotification;
use App\Models\Status;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;
use Tests\TestCase;

class LeadDetailTest extends TestCase
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

    private function assignedLead(User $vendor): Lead
    {
        return Lead::factory()->create([
            'vendor_id' => $vendor->id,
            'status_id' => Status::where('order', 2)->value('id'), // Asignado
        ]);
    }

    public function test_user_can_reply_to_lead_by_email(): void
    {
        Mail::fake();
        $admin = $this->userWithRole('administrador');
        $lead = Lead::factory()->create(['email' => 'cliente@example.com']);

        Volt::actingAs($admin)
            ->test('leads.show', ['lead' => $lead])
            ->set('reply_body', 'Hola, gracias por tu interés.')
            ->call('sendReply')
            ->assertHasNoErrors();

        Mail::assertSent(LeadReplyMail::class, fn ($m) => $m->hasTo('cliente@example.com'));
        $this->assertDatabaseHas('lead_messages', [
            'lead_id' => $lead->id,
            'direction' => 'out',
            'to_email' => 'cliente@example.com',
        ]);
    }

    public function test_reply_uses_tokenized_reply_to_when_mailbox_configured(): void
    {
        config(['services.leads_mailbox.address' => 'leads@proyectopublicitario.com.mx']);
        Mail::fake();
        $admin = $this->userWithRole('administrador');
        $lead = Lead::factory()->create(['email' => 'cliente@example.com']);

        Volt::actingAs($admin)
            ->test('leads.show', ['lead' => $lead])
            ->set('reply_body', 'Hola')
            ->call('sendReply')
            ->assertHasNoErrors();

        $lead->refresh();
        $this->assertNotNull($lead->reply_token);
        Mail::assertSent(LeadReplyMail::class, function ($m) use ($lead) {
            return $m->replyToAddress
                && $m->replyToAddress->address === 'leads+'.$lead->reply_token.'@proyectopublicitario.com.mx';
        });
    }

    public function test_reply_requires_body(): void
    {
        $admin = $this->userWithRole('administrador');
        $lead = Lead::factory()->create(['email' => 'cliente@example.com']);

        Volt::actingAs($admin)
            ->test('leads.show', ['lead' => $lead])
            ->set('reply_body', '')
            ->call('sendReply')
            ->assertHasErrors('reply_body');
    }

    public function test_registering_a_contact_marks_first_contact_and_advances_status(): void
    {
        $vendor = $this->userWithRole('vendedor');
        $lead = $this->assignedLead($vendor);

        Volt::actingAs($vendor)
            ->test('leads.show', ['lead' => $lead])
            ->set('type', 'llamada')
            ->set('comment', 'Contesta y pide cotización')
            ->call('addActivity')
            ->assertHasNoErrors();

        $lead->refresh();

        $this->assertNotNull($lead->first_contact_at);
        $this->assertSame(3, $lead->status->order); // avanzó a "Primer contacto"
        $this->assertDatabaseHas('activities', ['lead_id' => $lead->id, 'type' => 'llamada']);
        $this->assertDatabaseHas('timeline_events', ['lead_id' => $lead->id, 'type' => 'contact']);
    }

    public function test_internal_note_does_not_mark_first_contact(): void
    {
        $vendor = $this->userWithRole('vendedor');
        $lead = $this->assignedLead($vendor);

        Volt::actingAs($vendor)
            ->test('leads.show', ['lead' => $lead])
            ->set('type', 'nota')
            ->set('comment', 'Recordatorio interno')
            ->call('addActivity')
            ->assertHasNoErrors();

        $lead->refresh();

        $this->assertNull($lead->first_contact_at);
        $this->assertSame(2, $lead->status->order); // sigue en "Asignado"
    }

    public function test_scheduling_follow_up_sets_next_follow_up(): void
    {
        $vendor = $this->userWithRole('vendedor');
        $lead = $this->assignedLead($vendor);
        $when = now()->addDays(2)->format('Y-m-d\TH:i');

        Volt::actingAs($vendor)
            ->test('leads.show', ['lead' => $lead])
            ->set('type', 'whatsapp')
            ->set('comment', 'Quedamos de hablar')
            ->set('follow_up_at', $when)
            ->call('addActivity')
            ->assertHasNoErrors();

        $lead->refresh();

        $this->assertNotNull($lead->next_follow_up_at);
        $this->assertDatabaseHas('timeline_events', ['lead_id' => $lead->id, 'type' => 'follow_up']);
    }

    public function test_opening_lead_records_first_opened_and_marks_notification(): void
    {
        $vendor = $this->userWithRole('vendedor');
        $lead = $this->assignedLead($vendor);
        $notification = LeadNotification::create([
            'lead_id' => $lead->id,
            'vendor_id' => $vendor->id,
            'type' => 'nuevo_lead',
            'sent_at' => now(),
        ]);

        Volt::actingAs($vendor)->test('leads.show', ['lead' => $lead]);

        $lead->refresh();
        $notification->refresh();

        $this->assertNotNull($lead->first_opened_at);
        $this->assertNotNull($notification->opened_at);
        $this->assertDatabaseHas('timeline_events', ['lead_id' => $lead->id, 'type' => 'notification_opened']);
    }

    public function test_discarding_requires_reason_and_comment(): void
    {
        $vendor = $this->userWithRole('vendedor');
        $lead = $this->assignedLead($vendor);
        $descartado = Status::where('order', 13)->first(); // Descartado (final, no ganado)

        Volt::actingAs($vendor)
            ->test('leads.show', ['lead' => $lead])
            ->set('new_status_id', $descartado->id)
            ->call('changeStatus')
            ->assertHasErrors(['discard_reason_id', 'status_comment']);
    }

    public function test_discarding_with_reason_and_comment_succeeds(): void
    {
        $vendor = $this->userWithRole('vendedor');
        $lead = $this->assignedLead($vendor);
        $descartado = Status::where('order', 13)->first();
        $reasonId = DiscardReason::first()->id;

        Volt::actingAs($vendor)
            ->test('leads.show', ['lead' => $lead])
            ->set('new_status_id', $descartado->id)
            ->set('discard_reason_id', $reasonId)
            ->set('status_comment', 'No cumple presupuesto')
            ->call('changeStatus')
            ->assertHasNoErrors();

        $lead->refresh();

        $this->assertSame(13, $lead->status->order);
        $this->assertSame($reasonId, $lead->discard_reason_id);
        $this->assertSame('No cumple presupuesto', $lead->discard_comment);
        $this->assertDatabaseHas('lead_status_histories', ['lead_id' => $lead->id, 'to_status_id' => $descartado->id]);
    }

    public function test_vendor_cannot_view_another_vendors_lead(): void
    {
        $vendorA = $this->userWithRole('vendedor');
        $vendorB = $this->userWithRole('vendedor');
        $lead = $this->assignedLead($vendorB);

        $this->actingAs($vendorA)
            ->get(route('leads.show', $lead))
            ->assertForbidden();
    }

    public function test_supervisor_can_view_but_cannot_add_activity(): void
    {
        // El supervisor ve todo (leads.view.all) pero no gestiona actividad (§2).
        $supervisor = $this->userWithRole('supervisor');
        $vendor = $this->userWithRole('vendedor');
        $lead = $this->assignedLead($vendor);

        Volt::actingAs($supervisor)
            ->test('leads.show', ['lead' => $lead])
            ->set('type', 'llamada')
            ->set('comment', 'intento no autorizado')
            ->call('addActivity')
            ->assertForbidden();

        $this->assertDatabaseCount('activities', 0);
    }
}
