<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        if (! Schema::hasTable('jobs') || ! Schema::hasColumn('jobs', 'uuid')) {
            return;
        }

        // Ensure gen_random_uuid() is available on Postgres.
        DB::statement('create extension if not exists "pgcrypto"');
        DB::statement('alter table "jobs" alter column "uuid" set default gen_random_uuid()');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        if (! Schema::hasTable('jobs') || ! Schema::hasColumn('jobs', 'uuid')) {
            return;
        }

        DB::statement('alter table "jobs" alter column "uuid" drop default');
    }
};

