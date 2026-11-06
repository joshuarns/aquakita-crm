<?php

namespace App\Http\Middleware;

use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Establece la empresa (tenant) activa a partir del usuario autenticado.
 * El super-admin no queda acotado (gestiona todas las empresas).
 */
class SetCurrentCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_super_admin && $user->company_id) {
            app(Tenancy::class)->set($user->company_id);
        }

        return $next($request);
    }
}
