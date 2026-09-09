<?php

namespace Database\Seeders;

use App\Models\Status;
use Illuminate\Database\Seeder;

/** Los 13 estatus del flujo comercial (§7). */
class StatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            [1, 'Nuevo', 'Lead recién capturado.', false, false],
            [2, 'Asignado', 'El administrador designó vendedor.', false, false],
            [3, 'Primer contacto', 'El vendedor registró la primera comunicación.', false, false],
            [4, 'En seguimiento', 'Existe interés y próximas acciones.', false, false],
            [5, 'Reunión programada', 'Hay reunión o llamada de descubrimiento.', false, false],
            [6, 'Información solicitada', 'Se solicitaron datos o documentos del proyecto.', false, false],
            [7, 'Cotización enviada', 'Aquakita presentó una propuesta.', false, false],
            [8, 'Negociación', 'Se analizan condiciones comerciales.', false, false],
            [9, 'Venta cerrada', 'La oportunidad se convirtió en venta.', true, true],
            [10, 'No interesado', 'El prospecto confirmó que no desea continuar.', true, false],
            [11, 'Proyecto detenido', 'El proyecto permanece pospuesto.', false, false],
            [12, 'Sin respuesta', 'No ha sido posible establecer comunicación.', false, false],
            [13, 'Descartado', 'No cumple con los criterios comerciales.', true, false],
        ];

        foreach ($statuses as [$order, $name, $desc, $isFinal, $isWon]) {
            Status::updateOrCreate(
                ['order' => $order],
                ['name' => $name, 'description' => $desc, 'is_final' => $isFinal, 'is_won' => $isWon, 'active' => true],
            );
        }
    }
}
