<?php

namespace App\Support;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Construye la base de contactos para exportar a Mailchimp u otra
 * plataforma de correo masivo (§9). No envía correos: solo prepara
 * una base limpia aplicando las reglas de exclusión.
 */
class LeadExport
{
    /**
     * Campos del archivo de exportación (§9 "Campos de exportación").
     */
    public const HEADERS = [
        'Nombre', 'Apellido', 'Empresa', 'Correo electrónico',
        'País', 'Idioma', 'Tipo de proyecto', 'Estatus comercial', 'Vendedor asignado',
    ];

    /**
     * @param  array<string, int|null>  $filters
     */
    public function __construct(private array $filters = []) {}

    /**
     * Consulta base: solo contactos aptos (§9 reglas de exclusión) más filtros.
     */
    public function query(): Builder
    {
        $filterable = ['country_id', 'language_id', 'source_id', 'campaign_id', 'project_type_id', 'status_id', 'vendor_id'];

        return Lead::query()
            ->marketable()
            ->when(true, function ($q) use ($filterable) {
                foreach ($filterable as $field) {
                    if (! empty($this->filters[$field])) {
                        $q->where($field, $this->filters[$field]);
                    }
                }
            })
            ->with(['country', 'language', 'projectType', 'status', 'vendor']);
    }

    /**
     * Filas de datos ya sin duplicados por correo (§9 "Registros duplicados").
     *
     * @return Collection<int, array<int, string>>
     */
    public function rows(): Collection
    {
        return $this->query()
            ->orderBy('id')
            ->get()
            ->unique(fn (Lead $lead) => mb_strtolower(trim($lead->email)))
            ->values()
            ->map(fn (Lead $lead) => [
                $lead->first_name,
                $lead->last_name ?? '',
                $lead->company ?? '',
                $lead->email,
                $lead->country?->name ?? '',
                $lead->language?->name ?? '',
                $lead->projectType?->name ?? '',
                $lead->status?->name ?? '',
                $lead->vendor?->name ?? '',
            ]);
    }

    /** Número de contactos que se exportarían (para vista previa). */
    public function count(): int
    {
        return $this->rows()->count();
    }

    /**
     * Genera el contenido CSV (UTF-8 con BOM para que Excel lo abra bien).
     */
    public function toCsv(): string
    {
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, self::HEADERS, ',', '"', '');
        foreach ($this->rows() as $row) {
            fputcsv($handle, $row, ',', '"', '');
        }

        rewind($handle);
        $contents = stream_get_contents($handle);
        fclose($handle);

        return "\xEF\xBB\xBF".$contents; // BOM UTF-8
    }

    public function filename(): string
    {
        return 'contactos-aquakita-'.now()->format('Y-m-d').'.csv';
    }
}
