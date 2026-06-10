<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number');
            $table->string('status', 16)->default('issued'); // issued | applied | void
            $table->date('issue_date');
            $table->char('currency', 3);
            $table->decimal('amount', 15, 2);
            $table->string('memo')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'number']);
            $table->index(['user_id', 'status']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('credit_note_id')->nullable()->after('invoice_id')
                ->constrained('credit_notes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('credit_note_id');
        });

        Schema::dropIfExists('credit_notes');
    }
};
