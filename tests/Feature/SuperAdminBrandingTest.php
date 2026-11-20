<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use App\Support\Branding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Volt\Volt;
use Tests\TestCase;

class SuperAdminBrandingTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->create(['is_super_admin' => true, 'company_id' => null]);
    }

    public function test_branding_falls_back_to_defaults(): void
    {
        config(['app.name' => 'Mi CRM']);
        Branding::flush();

        $this->assertSame('Mi CRM', Branding::name());
        $this->assertSame('Sistema comercial Mi CRM', Branding::tagline());
        $this->assertFalse(Branding::hasLogo());
        $this->assertNotEmpty(Branding::logoBytes()); // el de la plataforma
    }

    public function test_super_admin_can_set_name_and_tagline(): void
    {
        Volt::actingAs($this->superAdmin())
            ->test('superadmin.branding')
            ->set('brand_name', 'Proyecto Publicitario')
            ->set('brand_tagline', 'Agencia de publicidad')
            ->call('save')
            ->assertHasNoErrors();

        Branding::flush();
        $this->assertSame('Proyecto Publicitario', Branding::name());
        $this->assertSame('Agencia de publicidad', Branding::tagline());
    }

    public function test_super_admin_can_upload_and_remove_logo(): void
    {
        $component = Volt::actingAs($this->superAdmin())
            ->test('superadmin.branding')
            ->set('logoFile', UploadedFile::fake()->image('logo.png', 120, 120))
            ->call('save')
            ->assertHasNoErrors();

        Branding::flush();
        $this->assertTrue(Branding::hasLogo());
        $this->assertDatabaseHas('app_settings', ['key' => 'brand_logo']);

        $component->call('removeLogo');

        Branding::flush();
        $this->assertFalse(Branding::hasLogo());
    }

    public function test_branding_route_serves_configured_logo(): void
    {
        Branding::set('brand_logo', base64_encode('fake-bytes'));

        $this->get(route('brand.logo'))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_non_super_admin_cannot_open_branding(): void
    {
        $user = User::factory()->create(['is_super_admin' => false]);

        $this->actingAs($user)
            ->get(route('superadmin.branding'))
            ->assertForbidden();
    }

    protected function tearDown(): void
    {
        AppSetting::query()->delete();
        Branding::flush();
        parent::tearDown();
    }
}
