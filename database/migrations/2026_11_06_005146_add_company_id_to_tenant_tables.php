<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tablas que pertenecen a una empresa (tenant). Se agregan nullable y se
     * rellenan en la migración de respaldo siguiente.
     *
     * @var array<int, string>
     */
    private array $tables = [
        'users', 'leads', 'activities', 'lead_status_histories', 'timeline_events',
        'lead_notifications', 'attachments', 'lead_forms',
        'sources', 'campaigns', 'project_types', 'discard_reasons',
        'languages', 'countries', 'cities', 'statuses', 'email_templates',
    ];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false)->after('active');
        });

        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_super_admin');
        });
    }
};
