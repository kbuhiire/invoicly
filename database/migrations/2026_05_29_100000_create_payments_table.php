<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Nullable so an unmatched payment can land before reconciliation
            // links it; nulled (not deleted) if its invoice is removed so the
            // money trail is preserved.
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3);
            $table->timestamp('paid_at');
            $table->string('reference')->nullable();   // bank/gateway memo used for matching
            $table->string('gateway')->nullable();
            $table->string('external_id')->nullable();  // gateway txn id, for idempotency
            $table->string('source', 16)->default('manual');         // PaymentSource
            $table->string('match_status', 16)->default('matched');  // PaymentMatchStatus
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'invoice_id', 'paid_at']);
            // Postgres allows multiple NULLs, so manual payments (no external_id)
            // are unaffected; gateway payments dedupe per user.
            $table->unique(['user_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
