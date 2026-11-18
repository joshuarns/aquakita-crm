<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ConversationsInboxTest extends TestCase
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

    private function leadWithMessage(string $name, string $channel, string $body): Lead
    {
        $lead = Lead::factory()->create(['first_name' => $name, 'last_name' => 'Zzqx']);
        $lead->messages()->create([
            'direction' => 'in',
            'channel' => $channel,
            'from_email' => 'x@y.com',
            'to_email' => 'buzon@empresa.com',
            'body' => $body,
        ]);

        return $lead;
    }

    public function test_inbox_lists_leads_with_messages(): void
    {
        $this->leadWithMessage('Zoraida', 'email', 'Correo de prueba');
        $this->leadWithMessage('Yolimar', 'whatsapp', 'WhatsApp de prueba');

        Volt::actingAs($this->admin())
            ->test('conversations.index')
            ->assertSee('Zoraida')
            ->assertSee('Yolimar');
    }

    public function test_channel_filter_shows_only_that_channel(): void
    {
        $this->leadWithMessage('Zoraida', 'email', 'Correo de prueba');
        $this->leadWithMessage('Yolimar', 'whatsapp', 'WhatsApp de prueba');

        Volt::actingAs($this->admin())
            ->test('conversations.index')
            ->set('channel', 'whatsapp')
            ->assertSee('Yolimar')
            ->assertDontSee('Zoraida');
    }

    public function test_leads_without_messages_are_not_listed(): void
    {
        Lead::factory()->create(['first_name' => 'Quintiliano']);

        Volt::actingAs($this->admin())
            ->test('conversations.index')
            ->assertDontSee('Quintiliano');
    }
}
