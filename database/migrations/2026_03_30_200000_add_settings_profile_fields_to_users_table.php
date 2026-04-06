<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('legal_first_name')->nullable()->after('name');
            $table->string('legal_last_name')->nullable()->after('legal_first_name');
            $table->date('date_of_birth')->nullable()->after('legal_last_name');
            $table->string('citizenship_country', 2)->nullable()->after('date_of_birth');
            $table->string('timezone', 64)->nullable()->after('citizenship_country');
            $table->string('tax_residence_country', 2)->nullable()->after('timezone');
            $table->string('contractor_subcategory', 32)->nullable()->after('tax_residence_country');
            $table->string('passport_id_number')->nullable()->after('contractor_subcategory');
            $table->string('tax_id')->nullable()->after('passport_id_number');
            $table->string('vat_id')->nullable()->after('tax_id');
            $table->json('personal_address')->nullable()->after('vat_id');
            $table->json('postal_address')->nullable()->after('personal_address');
        });

        $countries = config('countries', []);

        DB::table('users')->orderBy('id')->chunk(100, function ($rows) use ($countries) {
            foreach ($rows as $row) {
                $name = trim((string) ($row->name ?? ''));
                $first = $name;
                $last = '';
                if ($name !== '') {
                    $parts = preg_split('/\s+/', $name, 2, PREG_SPLIT_NO_EMPTY);
                    $first = $parts[0] ?? '';
                    $last = $parts[1] ?? '';
                }

                $countryCode = null;
                $legacyCountry = trim((string) ($row->country ?? ''));
                if ($legacyCountry !== '') {
                    if (strlen($legacyCountry) === 2 && isset($countries[strtoupper($legacyCountry)])) {
                        $countryCode = strtoupper($legacyCountry);
                    } else {
                        foreach ($countries as $code => $label) {
                            if (strcasecmp((string) $label, $legacyCountry) === 0) {
                                $countryCode = $code;
                                break;
                            }
                        }
                    }
                }

                $personalAddress = null;
                $addr = trim((string) ($row->address ?? ''));
                if ($addr !== '' || $countryCode !== null) {
                    $personalAddress = json_encode([
                        'line1' => $addr !== '' ? $addr : null,
                        'line2' => null,
                        'city' => null,
                        'region' => null,
                        'postal_code' => null,
                        'country_code' => $countryCode,
                    ]);
                }

                DB::table('users')->where('id', $row->id)->update([
                    'legal_first_name' => $first !== '' ? $first : null,
                    'legal_last_name' => $last !== '' ? $last : null,
                    'personal_address' => $personalAddress,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'legal_first_name',
                'legal_last_name',
                'date_of_birth',
                'citizenship_country',
                'timezone',
                'tax_residence_country',
                'contractor_subcategory',
                'passport_id_number',
                'tax_id',
                'vat_id',
                'personal_address',
                'postal_address',
            ]);
        });
    }
};
