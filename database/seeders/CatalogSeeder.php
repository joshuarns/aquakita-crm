<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\City;
use App\Models\Country;
use App\Models\DiscardReason;
use App\Models\EmailTemplate;
use App\Models\Language;
use App\Models\ProjectType;
use App\Models\Source;
use Illuminate\Database\Seeder;

/** Catálogos base de ejemplo (§3.2). Editables desde la app. */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([['es', 'Español'], ['en', 'Inglés'], ['pt', 'Portugués']] as [$code, $name]) {
            Language::firstOrCreate(['code' => $code], ['name' => $name]);
        }

        $countries = [
            'México' => ['Ciudad de México', 'Guadalajara', 'Monterrey'],
            'Colombia' => ['Bogotá', 'Medellín'],
            'España' => ['Madrid', 'Barcelona'],
            'Estados Unidos' => ['Miami', 'Houston'],
        ];
        foreach ($countries as $countryName => $cities) {
            $country = Country::firstOrCreate(['name' => $countryName]);
            foreach ($cities as $cityName) {
                City::firstOrCreate(['country_id' => $country->id, 'name' => $cityName]);
            }
        }

        foreach (['Sitio web', 'Google Ads', 'Facebook / Instagram', 'Referido', 'WhatsApp', 'Llamada directa'] as $s) {
            Source::firstOrCreate(['name' => $s]);
        }

        foreach (['Campaña general', 'Promoción temporada', 'Remarketing'] as $c) {
            Campaign::firstOrCreate(['name' => $c]);
        }

        foreach (['Residencial', 'Comercial', 'Industrial', 'Mantenimiento'] as $pt) {
            ProjectType::firstOrCreate(['name' => $pt]);
        }

        foreach (['Fuera de cobertura', 'Presupuesto insuficiente', 'Duplicado', 'Datos incompletos', 'No es el giro'] as $dr) {
            DiscardReason::firstOrCreate(['name' => $dr]);
        }

        $templates = [
            ['lead_recibido', 'Nuevo lead recibido: {{lead}}', 'Hola {{destinatario}}, llegó un nuevo lead: {{lead}} (origen: {{origen}}). Ya está en la bandeja general para asignarlo a un vendedor.'],
            ['nuevo_lead', 'Se te asignó un nuevo lead', 'Hola {{vendedor}}, se te asignó el lead {{lead}}. Ingresa al sistema para atenderlo.'],
            ['reasignacion', 'Un lead fue reasignado a ti', 'Hola {{vendedor}}, el lead {{lead}} fue reasignado a tu cuenta.'],
            ['seguimiento_proximo', 'Tienes un seguimiento próximo', 'Recuerda dar seguimiento a {{lead}} el {{fecha}}.'],
            ['seguimiento_vencido', 'Seguimiento vencido', 'El seguimiento de {{lead}} venció el {{fecha}} y sigue pendiente.'],
        ];
        foreach ($templates as [$key, $subject, $body]) {
            EmailTemplate::firstOrCreate(['key' => $key], ['subject' => $subject, 'body' => $body]);
        }
    }
}
