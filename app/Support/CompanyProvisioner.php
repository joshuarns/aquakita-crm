<?php

namespace App\Support;

use App\Models\Company;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\StatusSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Da de alta una empresa nueva: siembra sus catálogos propios y crea su primer
 * administrador, todo dentro del contexto (tenancy) de esa empresa.
 */
class CompanyProvisioner
{
    public function __construct(private Tenancy $tenancy) {}

    /**
     * @param  array{name: string, email: string, password: string}  $admin
     */
    public function provision(Company $company, array $admin): User
    {
        $previous = $this->tenancy->id();
        $this->tenancy->set($company->id);

        try {
            return DB::transaction(function () use ($company, $admin) {
                (new StatusSeeder)->run();
                (new CatalogSeeder)->run();

                $user = User::create([
                    'name' => $admin['name'],
                    'email' => $admin['email'],
                    'password' => Hash::make($admin['password']),
                    'active' => true,
                    'company_id' => $company->id,
                ]);
                $user->assignRole('administrador');

                return $user;
            });
        } finally {
            $this->tenancy->set($previous);
        }
    }
}
