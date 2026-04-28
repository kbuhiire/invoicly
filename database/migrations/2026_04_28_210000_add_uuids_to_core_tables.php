<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'users',
            'clients',
            'payment_methods',
            'invoices',
            'invoice_line_items',
            'recurring_invoices',
            'jobs',
            'personal_access_tokens',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->uuid('uuid')->nullable()->unique()->after('id');
            });
        }

        // Backfill UUIDs for existing rows (best-effort, chunked).
        foreach ($tables as $table) {
            DB::table($table)
                ->whereNull('uuid')
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($table) {
                    foreach ($rows as $row) {
                        DB::table($table)
                            ->where('id', $row->id)
                            ->update(['uuid' => (string) Str::uuid()]);
                    }
                });
        }

        // Make uuid non-nullable after backfill (avoid ->change() to not require doctrine/dbal).
        foreach ($tables as $table) {
            DB::statement("alter table \"{$table}\" alter column \"uuid\" set not null");
        }
    }

    public function down(): void
    {
        $tables = [
            'users',
            'clients',
            'payment_methods',
            'invoices',
            'invoice_line_items',
            'recurring_invoices',
            'jobs',
            'personal_access_tokens',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropUnique(['uuid']);
                $table->dropColumn('uuid');
            });
        }
    }
};

