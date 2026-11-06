<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use App\Support\BrandLogo;
use App\Support\Tenancy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Roles y permisos (globales).
        $this->call(RolePermissionSeeder::class);

        // Empresa #1 con los catálogos y usuarios demo dentro de su tenancy.
        $company = Company::firstOrCreate(
            ['slug' => 'aquakita'],
            [
                'name' => config('app.name', 'Aquakita'),
                'brand_color' => '#0e7490',
                'timezone' => config('app.timezone', 'America/Mexico_City'),
                'locale' => 'es',
                'logo' => base64_encode(BrandLogo::bytes()),
                'active' => true,
            ]
        );

        $tenancy = app(Tenancy::class);
        $tenancy->set($company->id);

        try {
            $this->call([
                StatusSeeder::class,
                CatalogSeeder::class,
                DemoUsersSeeder::class,
            ]);
        } finally {
            $tenancy->set(null);
        }

        // Super-admin de la plataforma (demo).
        User::firstOrCreate(
            ['email' => 'super@aquakita.test'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'active' => true,
                'is_super_admin' => true,
                'company_id' => null,
            ]
        );
    }
}
