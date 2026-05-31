<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Outbound subscriptions: where Invoicly POSTs signed event payloads when
        // domain events (payment.received, invoice.reconciled, …) fire for a user.
        Schema::create('webhook_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('url');
            $table->string('secret');                 // signs the X-Invoicly-Signature header
            $table->json('events');                   // list of subscribed event names
            $table->boolean('active')->default(true);
            $table->unsignedInteger('failure_count')->default(0);
            $table->unsignedSmallInteger('last_status')->nullable(); // last HTTP status seen
            $table->timestamp('last_dispatched_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_subscriptions');
    }
};
