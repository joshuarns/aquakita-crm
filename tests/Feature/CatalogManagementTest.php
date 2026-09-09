<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Country;
use App\Models\EmailTemplate;
use App\Models\Language;
use App\Models\Source;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CatalogManagementTest extends TestCase
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

    public function test_admin_can_add_a_source(): void
    {
        Volt::actingAs($this->admin())
            ->test('catalogs.manage', ['type' => 'sources'])
            ->set('form.name', 'TikTok')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('sources', ['name' => 'TikTok', 'active' => true]);
    }

    public function test_language_code_must_be_unique(): void
    {
        Language::create(['code' => 'es', 'name' => 'Español']);

        Volt::actingAs($this->admin())
            ->test('catalogs.manage', ['type' => 'languages'])
            ->set('form.code', 'es')
            ->set('form.name', 'Español duplicado')
            ->call('save')
            ->assertHasErrors(['form.code']);
    }

    public function test_admin_can_toggle_active(): void
    {
        $source = Source::create(['name' => 'Feria', 'active' => true]);

        Volt::actingAs($this->admin())
            ->test('catalogs.manage', ['type' => 'sources'])
            ->call('toggle', $source->id);

        $this->assertFalse($source->fresh()->active);
    }

    public function test_unknown_catalog_type_returns_404(): void
    {
        $this->actingAs($this->admin())
            ->get(route('catalogs.manage', ['type' => 'inexistente']))
            ->assertNotFound();
    }

    public function test_capturista_cannot_access_config(): void
    {
        $user = User::factory()->create();
        $user->assignRole('capturista');

        $this->actingAs($user)->get(route('catalogs.index'))->assertForbidden();
    }

    public function test_admin_can_add_a_city_to_a_country(): void
    {
        $country = Country::create(['name' => 'México']);

        Volt::actingAs($this->admin())
            ->test('catalogs.cities')
            ->set('country_id', $country->id)
            ->set('name', 'Puebla')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('cities', ['name' => 'Puebla', 'country_id' => $country->id, 'active' => true]);
    }

    public function test_city_requires_a_country(): void
    {
        Volt::actingAs($this->admin())
            ->test('catalogs.cities')
            ->set('name', 'Sin País')
            ->call('save')
            ->assertHasErrors(['country_id']);
    }

    public function test_cities_can_be_filtered_by_country(): void
    {
        $mx = Country::create(['name' => 'México']);
        $cl = Country::create(['name' => 'Chile']);
        City::create(['country_id' => $mx->id, 'name' => 'Toluca']);
        City::create(['country_id' => $cl->id, 'name' => 'Valparaíso']);

        Volt::actingAs($this->admin())
            ->test('catalogs.cities')
            ->set('filterCountry', $mx->id)
            ->assertSee('Toluca')
            ->assertDontSee('Valparaíso');
    }

    public function test_admin_can_edit_email_template(): void
    {
        $template = EmailTemplate::create([
            'key' => 'nuevo_lead',
            'subject' => 'Viejo asunto',
            'body' => 'Viejo cuerpo {{lead}}',
        ]);

        Volt::actingAs($this->admin())
            ->test('catalogs.templates')
            ->call('edit', $template->id)
            ->set('subject', 'Nuevo asunto')
            ->set('body', 'Hola {{vendedor}}')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('email_templates', ['id' => $template->id, 'subject' => 'Nuevo asunto']);
    }
}
