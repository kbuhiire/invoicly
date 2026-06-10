<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('number');
            $table->string('status', 16)->default('draft'); // draft | sent | accepted | declined (expired is derived)
            $table->date('issue_date');
            $table->date('expiry_date')->nullable();
            $table->char('currency', 3);
            $table->decimal('amount', 15, 2);
            $table->decimal('vat_amount', 15, 2)->nullable();
            $table->foreignId('tax_rate_id')->nullable()->constrained('tax_rates')->nullOnDelete();
            $table->text('payer_memo')->nullable();
            $table->foreignId('converted_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'number']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('quote_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 15, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_line_items');
        Schema::dropIfExists('quotes');
    }
};
