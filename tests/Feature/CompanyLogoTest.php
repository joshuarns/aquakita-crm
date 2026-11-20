<?php

namespace Tests\Feature;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CompanyLogoTest extends TestCase
{
    use RefreshDatabase;

    private function company(?string $logo = null): Company
    {
        return Company::create([
            'name' => 'ACME',
            'slug' => 'acme-'.Str::random(6),
            'brand_color' => '#0e7490',
            'timezone' => 'America/Mexico_City',
            'locale' => 'es',
            'active' => true,
            'logo' => $logo,
        ]);
    }

    public function test_company_without_logo_serves_neutral_placeholder(): void
    {
        $company = $this->company();

        $this->assertSame('image/svg+xml', $company->logoContentType());

        $response = $this->get(route('company.logo', $company))->assertOk();
        $this->assertStringContainsString('image/svg+xml', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('<svg', $response->getContent());
    }

    public function test_company_with_logo_serves_it_as_png(): void
    {
        $company = $this->company(base64_encode('fake-bytes'));

        $this->assertSame('image/png', $company->logoContentType());

        $this->get(route('company.logo', $company))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }
}
