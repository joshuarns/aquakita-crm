<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Lead;
use App\Models\User;
use App\Support\LeadExport;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class LeadExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(StatusSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('administrador');

        return $user;
    }

    public function test_export_excludes_contacts_without_email(): void
    {
        Lead::factory()->create(['email' => 'con@correo.test']);
        Lead::factory()->create(['email' => null]);

        $rows = (new LeadExport)->rows();

        $this->assertCount(1, $rows);
    }

    public function test_export_excludes_duplicate_emails(): void
    {
        Lead::factory()->create(['email' => 'dup@correo.test']);
        Lead::factory()->create(['email' => 'DUP@correo.test']); // mismo correo, otra caja

        $this->assertSame(1, (new LeadExport)->count());
    }

    public function test_export_excludes_no_marketing_and_unsubscribed(): void
    {
        Lead::factory()->create(['email' => 'ok@correo.test']);
        Lead::factory()->create(['email' => 'nopublicidad@correo.test', 'no_marketing' => true]);
        Lead::factory()->create(['email' => 'baja@correo.test', 'unsubscribed' => true]);

        $rows = (new LeadExport)->rows();

        $this->assertCount(1, $rows);
        $this->assertSame('ok@correo.test', $rows->first()[3]); // columna Correo
    }

    public function test_export_applies_country_filter(): void
    {
        $lead = Lead::factory()->create(['email' => 'mx@correo.test', 'country_id' => Country::create(['name' => 'México'])->id]);
        Lead::factory()->create(['email' => 'otro@correo.test', 'country_id' => Country::create(['name' => 'Chile'])->id]);

        $count = (new LeadExport(['country_id' => $lead->country_id]))->count();

        $this->assertSame(1, $count);
    }

    public function test_csv_has_headers_and_bom(): void
    {
        Lead::factory()->create(['first_name' => 'Ana', 'email' => 'ana@correo.test']);

        $csv = (new LeadExport)->toCsv();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv); // BOM UTF-8
        $this->assertStringContainsString('Correo electrónico', $csv);
        $this->assertStringContainsString('ana@correo.test', $csv);
    }

    public function test_admin_can_download_csv(): void
    {
        Lead::factory()->create(['email' => 'ana@correo.test']);

        Volt::actingAs($this->admin())
            ->test('leads.export')
            ->call('download')
            ->assertFileDownloaded('contactos-aquakita-'.now()->format('Y-m-d').'.csv');
    }

    public function test_admin_can_download_xlsx(): void
    {
        Excel::fake();
        Lead::factory()->create(['email' => 'ana@correo.test']);

        Volt::actingAs($this->admin())
            ->test('leads.export')
            ->set('format', 'xlsx')
            ->call('download');

        Excel::assertDownloaded('contactos-aquakita-'.now()->format('Y-m-d').'.xlsx');
    }

    public function test_vendor_cannot_access_export_screen(): void
    {
        $vendor = User::factory()->create();
        $vendor->assignRole('vendedor');

        $this->actingAs($vendor)
            ->get(route('leads.export'))
            ->assertForbidden();
    }
}
