<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 64);
            $table->decimal('rate', 6, 3);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_default']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('tax_rate_id')->nullable()->after('vat_amount')
                ->constrained('tax_rates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tax_rate_id');
        });

        Schema::dropIfExists('tax_rates');
    }
};
