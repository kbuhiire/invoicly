<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('invoice_show_signature')->default(false)->after('invoice_signature_path');
            $table->string('invoice_signature_type', 16)->default('digital')->after('invoice_show_signature');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['invoice_show_signature', 'invoice_signature_type']);
        });
    }
};
