<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('vat_amount', 15, 2)->nullable()->after('amount');
            $table->date('due_date')->nullable()->after('issue_date');
            $table->date('period_from')->nullable()->after('due_date');
            $table->date('period_to')->nullable()->after('period_from');
            $table->text('payer_memo')->nullable();
            $table->text('payment_details')->nullable();
            $table->string('invoice_type', 100)->nullable()->default('Service');
            $table->string('vat_id', 100)->nullable();
            $table->string('tax_id', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'vat_amount',
                'due_date',
                'period_from',
                'period_to',
                'payer_memo',
                'payment_details',
                'invoice_type',
                'vat_id',
                'tax_id',
            ]);
        });
    }
};
