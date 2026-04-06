<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('invoice_use_personal_phone')->default(true)->after('invoice_address');
            $table->string('invoice_phone_dial_code', 8)->nullable()->after('invoice_use_personal_phone');
            $table->string('invoice_phone_national', 30)->nullable()->after('invoice_phone_dial_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['invoice_use_personal_phone', 'invoice_phone_dial_code', 'invoice_phone_national']);
        });
    }
};
