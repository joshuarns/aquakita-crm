<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

/**
 * Separación estricta de acceso a leads (§2, §4.2).
 */
class LeadPolicy
{
    /**
     * Quién puede ver la ficha de un lead concreto.
     * - Admin/supervisor: cualquiera (leads.view.all).
     * - Vendedor: solo los asignados a su cuenta.
     * - Capturista: solo los que él capturó.
     */
    public function view(User $user, Lead $lead): bool
    {
        if ($user->can('leads.view.all')) {
            return true;
        }

        if ($user->hasRole('vendedor')) {
            return $lead->vendor_id === $user->id;
        }

        return $lead->captured_by === $user->id;
    }

    /**
     * Quién puede registrar actividad y cambiar el estatus de un lead.
     * Solo el vendedor asignado (§6, §10) o el administrador.
     */
    public function manage(User $user, Lead $lead): bool
    {
        if ($user->can('leads.assign')) {
            return true; // administrador
        }

        return $user->hasRole('vendedor')
            && $user->can('activities.manage')
            && $lead->vendor_id === $user->id;
    }
}
