<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_forms', function (Blueprint $table) {
            $table->string('whatsapp_label', 60)->nullable()->after('whatsapp_number');
        });
    }

    public function down(): void
    {
        Schema::table('lead_forms', function (Blueprint $table) {
            $table->dropColumn('whatsapp_label');
        });
    }
};
