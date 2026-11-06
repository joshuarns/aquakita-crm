<?php

namespace App\Support;

/**
 * Contexto de la empresa (tenant) activa en la petición actual.
 * Cuando hay una empresa establecida, el scope global filtra todas las
 * consultas y se rellena company_id al crear registros.
 */
class Tenancy
{
    private ?int $companyId = null;

    public function set(?int $companyId): void
    {
        $this->companyId = $companyId;
    }

    public function id(): ?int
    {
        return $this->companyId;
    }

    public function check(): bool
    {
        return $this->companyId !== null;
    }

    /**
     * Ejecuta un callback sin el filtro de empresa (p. ej. tareas del super-admin).
     */
    public function withoutScope(callable $callback): mixed
    {
        $previous = $this->companyId;
        $this->companyId = null;

        try {
            return $callback();
        } finally {
            $this->companyId = $previous;
        }
    }
}
