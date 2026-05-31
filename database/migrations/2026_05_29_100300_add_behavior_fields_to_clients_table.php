<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Cached payment-behaviour aggregates, recomputed by
            // RecalculateClientBehavior (nightly + on each payment received).
            $table->unsignedInteger('paid_invoice_count')->default(0)->after('whatsapp_opt_in');
            $table->unsignedInteger('avg_days_to_pay')->nullable()->after('paid_invoice_count'); // median days issue→paid
            $table->unsignedTinyInteger('on_time_rate')->nullable()->after('avg_days_to_pay');   // 0-100
            $table->unsignedInteger('late_count')->default(0)->after('on_time_rate');
            $table->unsignedTinyInteger('credit_score')->nullable()->after('late_count');        // 0-100, higher = safer
            $table->string('credit_risk_level', 8)->nullable()->after('credit_score');           // low|medium|high
            $table->boolean('flagged_for_review')->default(false)->after('credit_risk_level');
            $table->timestamp('behavior_recomputed_at')->nullable()->after('flagged_for_review');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'paid_invoice_count',
                'avg_days_to_pay',
                'on_time_rate',
                'late_count',
                'credit_score',
                'credit_risk_level',
                'flagged_for_review',
                'behavior_recomputed_at',
            ]);
        });
    }
};
