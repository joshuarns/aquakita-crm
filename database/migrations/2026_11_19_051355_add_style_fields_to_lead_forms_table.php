<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_forms', function (Blueprint $table) {
            $table->string('bg_color', 20)->nullable()->after('accent_color');
            $table->string('text_color', 20)->nullable()->after('bg_color');
            $table->string('border_color', 20)->nullable()->after('text_color');
            $table->unsignedSmallInteger('border_radius')->nullable()->after('border_color');
            $table->unsignedSmallInteger('padding')->nullable()->after('border_radius');
            $table->unsignedSmallInteger('field_radius')->nullable()->after('padding');
        });
    }

    public function down(): void
    {
        Schema::table('lead_forms', function (Blueprint $table) {
            $table->dropColumn(['bg_color', 'text_color', 'border_color', 'border_radius', 'padding', 'field_radius']);
        });
    }
};
