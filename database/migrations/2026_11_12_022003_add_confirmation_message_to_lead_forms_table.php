<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_forms', function (Blueprint $table) {
            $table->string('confirmation_subject')->nullable()->after('submit_label');
            $table->text('confirmation_body')->nullable()->after('confirmation_subject');
        });
    }

    public function down(): void
    {
        Schema::table('lead_forms', function (Blueprint $table) {
            $table->dropColumn(['confirmation_subject', 'confirmation_body']);
        });
    }
};
