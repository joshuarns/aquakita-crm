<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Lead;
use App\Models\Source;
use App\Models\Status;
use App\Support\CompanyProvisioner;
use App\Support\Tenancy;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyTenancyTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app(Tenancy::class)->set(null);
        parent::tearDown();
    }

    public function test_provision_creates_scoped_catalogs_and_admin(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'brand_color' => '#000', 'active' => true]);
        $admin = app(CompanyProvisioner::class)->provision($company, [
            'name' => 'Admin Acme', 'email' => 'admin@acme.test', 'password' => 'password12',
        ]);

        $this->assertTrue($admin->hasRole('administrador'));
        $this->assertSame($company->id, $admin->company_id);

        app(Tenancy::class)->set($company->id);
        $this->assertGreaterThan(0, Status::count());
        $this->assertGreaterThan(0, Source::count());
    }

    public function test_data_is_isolated_between_companies(): void
    {
        $t = app(Tenancy::class);
        $c1 = Company::create(['name' => 'Uno', 'slug' => 'uno', 'brand_color' => '#111', 'active' => true]);
        $c2 = Company::create(['name' => 'Dos', 'slug' => 'dos', 'brand_color' => '#222', 'active' => true]);

        $t->set($c1->id);
        $s1 = Status::create(['order' => 1, 'name' => 'Nuevo', 'is_final' => false, 'is_won' => false, 'active' => true]);
        Lead::create(['first_name' => 'Lead Uno', 'status_id' => $s1->id]);

        $t->set($c2->id);
        $s2 = Status::create(['order' => 1, 'name' => 'Nuevo', 'is_final' => false, 'is_won' => false, 'active' => true]);
        Lead::create(['first_name' => 'Lead Dos', 'status_id' => $s2->id]);

        $t->set($c1->id);
        $this->assertSame(1, Lead::count());
        $this->assertSame('Lead Uno', Lead::first()->first_name);

        $t->set($c2->id);
        $this->assertSame(1, Lead::count());
        $this->assertSame('Lead Dos', Lead::first()->first_name);

        // Sin tenant (super-admin) ve ambas.
        $t->set(null);
        $this->assertSame(2, Lead::count());
    }

    public function test_created_record_gets_current_company_id(): void
    {
        $c = Company::create(['name' => 'Tri', 'slug' => 'tri', 'brand_color' => '#333', 'active' => true]);
        app(Tenancy::class)->set($c->id);

        $source = Source::create(['name' => 'Sitio web']);

        $this->assertSame($c->id, $source->company_id);
    }
}
