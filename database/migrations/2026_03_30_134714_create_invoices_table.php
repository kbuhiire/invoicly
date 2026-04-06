<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('number')->unique();
            $table->date('issue_date');
            $table->string('status', 32);
            $table->string('currency', 3);
            $table->decimal('amount', 15, 2);
            $table->decimal('amount_secondary', 15, 2)->nullable();
            $table->string('currency_secondary', 3)->nullable();
            $table->string('attachment_path')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'issue_date']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
