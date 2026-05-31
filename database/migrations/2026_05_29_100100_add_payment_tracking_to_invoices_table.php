<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Denormalised cache of SUM(payments.amount), maintained by PaymentService.
            $table->decimal('amount_paid', 15, 2)->default(0)->after('amount');
            // Set when the invoice becomes fully paid (latest payment date).
            $table->timestamp('paid_at')->nullable()->after('status');
            // Gates smart-reminder re-sends (Phase 4).
            $table->timestamp('last_reminder_sent_at')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['amount_paid', 'paid_at', 'last_reminder_sent_at']);
        });
    }
};
