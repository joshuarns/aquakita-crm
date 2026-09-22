<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Formularios web embebibles (captación web-to-lead).
     */
    public function up(): void
    {
        Schema::create('lead_forms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('token', 40)->unique();
            $table->foreignId('source_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->json('fields')->nullable();
            $table->text('success_message')->nullable();
            $table->string('redirect_url')->nullable();
            $table->json('allowed_domains')->nullable();
            $table->string('accent_color', 20)->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('submissions_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_forms');
    }
};
