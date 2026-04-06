<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('name');
            $table->string('frequency', 16);
            $table->tinyInteger('day_of_month')->nullable();
            $table->date('next_run_at');
            $table->date('last_run_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_active', 'next_run_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_invoices');
    }
};
