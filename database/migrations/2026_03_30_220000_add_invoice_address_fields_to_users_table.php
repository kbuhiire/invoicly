<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('invoice_use_personal_address')->default(true)->after('invoice_type');
            $table->json('invoice_address')->nullable()->after('invoice_use_personal_address');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['invoice_use_personal_address', 'invoice_address']);
        });
    }
};
