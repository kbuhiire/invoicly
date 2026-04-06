<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->boolean('is_business')->default(false)->after('type');
            $table->string('first_name')->nullable()->after('is_business');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('business_name')->nullable()->after('last_name');
            $table->string('country', 2)->nullable()->after('business_name');
            $table->string('street')->nullable()->after('country');
            $table->string('city')->nullable()->after('street');
            $table->string('postal_code')->nullable()->after('city');
            $table->string('email')->nullable()->after('postal_code');
            $table->string('vat_number')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'is_business',
                'first_name',
                'last_name',
                'business_name',
                'country',
                'street',
                'city',
                'postal_code',
                'email',
                'vat_number',
            ]);
        });
    }
};
