<?php

namespace Tests\Feature;

use App\Mail\LeadReceivedMail;
use App\Models\Company;
use App\Models\Lead;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MailBrandingTest extends TestCase
{
    use RefreshDatabase;

    private function company(string $name): Company
    {
        return Company::create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.uniqid(),
            'brand_color' => '#123456',
            'timezone' => 'America/Mexico_City',
            'locale' => 'es',
            'active' => true,
        ]);
    }

    public function test_mail_shows_company_name_and_never_the_platform_logo(): void
    {
        $company = $this->company('MANT ELEK');

        app(Tenancy::class)->set($company->id);
        $lead = Lead::factory()->create(['company_id' => $company->id]);
        app(Tenancy::class)->set(null);

        $html = (new LeadReceivedMail($lead))->render();

        // Sin logo propio, la cabecera muestra el nombre de la empresa…
        $this->assertStringContainsString('MANT ELEK', $html);
        // …y nunca incrusta una imagen (que sería el logo por defecto de la plataforma).
        $this->assertStringNotContainsString('cid:', $html);
    }
}
