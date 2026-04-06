<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('invoice_show_email')->default(false)->after('postal_address');
            $table->boolean('invoice_show_phone')->default(true)->after('invoice_show_email');
            $table->text('invoice_note')->nullable()->after('invoice_show_phone');
            $table->string('invoice_signature_path')->nullable()->after('invoice_note');
            $table->string('invoice_type', 32)->default('service')->after('invoice_signature_path');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'invoice_show_email',
                'invoice_show_phone',
                'invoice_note',
                'invoice_signature_path',
                'invoice_type',
            ]);
        });
    }
};
