<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Crea (o promueve) un super-admin de la plataforma.
 * Uso: php artisan app:create-super-admin "Nombre" correo@dominio.com "contraseña"
 */
class CreateSuperAdmin extends Command
{
    protected $signature = 'app:create-super-admin {name} {email} {password}';

    protected $description = 'Crea o promueve un super-admin (dueño de la plataforma)';

    public function handle(): int
    {
        $user = User::updateOrCreate(
            ['email' => $this->argument('email')],
            [
                'name' => $this->argument('name'),
                'password' => Hash::make($this->argument('password')),
                'active' => true,
                'is_super_admin' => true,
                'company_id' => null,
            ]
        );

        $this->info("Super-admin listo: {$user->email}");

        return self::SUCCESS;
    }
}
