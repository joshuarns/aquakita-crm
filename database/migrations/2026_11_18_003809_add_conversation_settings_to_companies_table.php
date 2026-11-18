<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('conversations_enabled')->default(false)->after('from_email');
            $table->string('reply_from_email')->nullable()->after('conversations_enabled');
            $table->string('imap_host')->nullable()->after('reply_from_email');
            $table->unsignedSmallInteger('imap_port')->nullable()->after('imap_host');
            $table->string('imap_username')->nullable()->after('imap_port');
            $table->text('imap_password')->nullable()->after('imap_username'); // cifrada (cast encrypted)
            $table->string('imap_encryption', 10)->nullable()->default('ssl')->after('imap_password');
            $table->string('imap_folder')->nullable()->default('INBOX')->after('imap_encryption');
            $table->boolean('imap_validate_cert')->default(true)->after('imap_folder');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'conversations_enabled', 'reply_from_email', 'imap_host', 'imap_port',
                'imap_username', 'imap_password', 'imap_encryption', 'imap_folder', 'imap_validate_cert',
            ]);
        });
    }
};
