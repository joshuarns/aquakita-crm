<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $tables = [
        'users', 'leads', 'activities', 'lead_status_histories', 'timeline_events',
        'lead_notifications', 'attachments', 'lead_forms',
        'sources', 'campaigns', 'project_types', 'discard_reasons',
        'languages', 'countries', 'cities', 'statuses', 'email_templates',
    ];

    /**
     * Crea la primera empresa con los datos existentes y la asigna a todo.
     */
    public function up(): void
    {
        if (DB::table('companies')->count() > 0) {
            return;
        }

        $companyId = DB::table('companies')->insertGetId([
            'name' => config('app.name', 'Aquakita'),
            'slug' => 'aquakita',
            'brand_color' => '#0e7490',
            'timezone' => config('app.timezone', 'America/Mexico_City'),
            'locale' => 'es',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($this->tables as $name) {
            DB::table($name)->whereNull('company_id')->update(['company_id' => $companyId]);
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $name) {
            DB::table($name)->update(['company_id' => null]);
        }

        DB::table('companies')->where('slug', 'aquakita')->delete();
    }
};
