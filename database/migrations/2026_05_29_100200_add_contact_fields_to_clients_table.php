<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Pulled forward from Phase 4 so smart reminders can reach clients
            // over SMS / WhatsApp as well as email.
            $table->string('phone')->nullable()->after('email');
            $table->string('preferred_contact_method', 16)->default('email')->after('phone'); // email|sms|whatsapp|slack
            $table->boolean('whatsapp_opt_in')->default(false)->after('preferred_contact_method');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['phone', 'preferred_contact_method', 'whatsapp_opt_in']);
        });
    }
};
