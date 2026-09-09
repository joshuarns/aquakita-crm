<?php

namespace App\Exports;

use App\Support\LeadExport;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Hoja de cálculo de contactos para exportar a Excel (§9).
 * Reutiliza LeadExport para aplicar filtros y las reglas de exclusión.
 */
class LeadsSheet implements FromArray, ShouldAutoSize, WithHeadings
{
    public function __construct(private LeadExport $export) {}

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return LeadExport::HEADERS;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        return $this->export->rows()->all();
    }
}
