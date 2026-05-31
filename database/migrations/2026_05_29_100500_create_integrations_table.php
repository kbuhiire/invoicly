<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Inbound integrations: a per-user endpoint + signing secret that an
        // external system (payment gateway, bank, PSP) posts to. The uuid is the
        // public webhook path component; the signing_secret verifies the HMAC.
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 40)->default('generic'); // generic|stripe|bank|…
            $table->string('name');
            $table->string('signing_secret');
            $table->boolean('active')->default(true);
            $table->timestamp('last_event_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations');
    }
};
