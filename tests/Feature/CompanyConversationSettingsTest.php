<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CompanyConversationSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function companyAdmin(): array
    {
        $company = Company::create([
            'name' => 'Empresa Z',
            'slug' => 'empresa-z-'.uniqid(),
            'brand_color' => '#0e7490',
            'timezone' => 'America/Mexico_City',
            'locale' => 'es',
            'active' => true,
        ]);

        $user = User::factory()->create(['company_id' => $company->id]);
        $user->assignRole('administrador');

        return [$company, $user];
    }

    public function test_admin_can_configure_imap_and_password_is_encrypted_at_rest(): void
    {
        [$company, $user] = $this->companyAdmin();

        Volt::actingAs($user)
            ->test('catalogs.company')
            ->set('conversations_enabled', true)
            ->set('reply_from_email', 'info@tuempresa.com')
            ->set('imap_host', 'imap.gmail.com')
            ->set('imap_port', 993)
            ->set('imap_username', 'info@tuempresa.com')
            ->set('imap_password', 'secreto-123')
            ->call('save')
            ->assertHasNoErrors();

        $company->refresh();
        $this->assertTrue($company->conversations_enabled);
        $this->assertSame('info@tuempresa.com', $company->reply_from_email);
        $this->assertTrue($company->conversationsReady());

        // El cast descifra; el valor crudo en la base NO es texto plano.
        $this->assertSame('secreto-123', $company->imap_password);
        $raw = DB::table('companies')->where('id', $company->id)->value('imap_password');
        $this->assertNotEmpty($raw);
        $this->assertStringNotContainsString('secreto-123', (string) $raw);
    }

    public function test_blank_password_keeps_the_existing_one(): void
    {
        [$company, $user] = $this->companyAdmin();
        $company->update(['imap_password' => 'original-pass']);

        Volt::actingAs($user)
            ->test('catalogs.company')
            ->set('conversations_enabled', true)
            ->set('imap_host', 'imap.gmail.com')
            ->set('imap_username', 'info@tuempresa.com')
            ->set('imap_password', '') // no se escribe nueva
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('original-pass', $company->refresh()->imap_password);
    }

    public function test_reply_from_prefers_company_mailbox(): void
    {
        [$company] = $this->companyAdmin();
        $company->update(['reply_from_email' => 'info@tuempresa.com']);

        $this->assertSame('info@tuempresa.com', $company->replyFromAddress());
    }
}
