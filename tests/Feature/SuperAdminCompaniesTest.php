<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Lead;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Volt\Volt;
use Tests\TestCase;

class SuperAdminCompaniesTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->create(['is_super_admin' => true, 'company_id' => null]);
    }

    private function company(string $name = 'ACME'): Company
    {
        return Company::create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.uniqid(),
            'brand_color' => '#0e7490',
            'timezone' => 'America/Mexico_City',
            'locale' => 'es',
            'active' => true,
        ]);
    }

    public function test_delete_requires_exact_name_confirmation(): void
    {
        $company = $this->company('ACME');

        Volt::actingAs($this->superAdmin())
            ->test('superadmin.companies')
            ->call('confirmDelete', $company->id)
            ->set('deleteConfirm', 'nombre incorrecto')
            ->call('destroy')
            ->assertHasErrors('deleteConfirm');

        $this->assertDatabaseHas('companies', ['id' => $company->id]);
    }

    public function test_super_admin_can_delete_company_and_its_data(): void
    {
        $company = $this->company('ACME');

        app(Tenancy::class)->set($company->id);
        $lead = Lead::factory()->create(['company_id' => $company->id]);
        app(Tenancy::class)->set(null);

        Volt::actingAs($this->superAdmin())
            ->test('superadmin.companies')
            ->call('confirmDelete', $company->id)
            ->set('deleteConfirm', 'ACME')
            ->call('destroy')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('companies', ['id' => $company->id]);
        $this->assertDatabaseMissing('leads', ['id' => $lead->id]); // cascada
    }
}
