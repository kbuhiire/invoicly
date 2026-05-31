<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();

            $table->string('type', 16);                    // upcoming | overdue
            // Stage identity used for idempotency, e.g. "upcoming" or "overdue:2".
            $table->string('dedupe_key');
            $table->string('channel', 16)->nullable();     // email | sms | whatsapp | slack
            $table->string('status', 16);                  // sent | failed | skipped
            // Twilio Message SID / Slack message ts — joins to Phase 5 status webhooks.
            $table->string('provider_message_sid')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            // One row per invoice per reminder stage — guarantees a stage is
            // never delivered twice even if the scheduler runs more than once.
            $table->unique(['invoice_id', 'dedupe_key']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_logs');
    }
};
