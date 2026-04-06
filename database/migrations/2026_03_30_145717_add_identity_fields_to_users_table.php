<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('preferred_currency');
            $table->text('address')->nullable()->after('phone');
            $table->string('country')->nullable()->after('address');
            $table->string('logo_path')->nullable()->after('country');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'address', 'country', 'logo_path']);
        });
    }
};
