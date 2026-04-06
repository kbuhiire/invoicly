<?php

namespace App\Support;

class AddressNormalization
{
    /**
     * @param  array<string, mixed>|null  $addr
     * @return array<string, string|null>|null
     */
    public static function normalize(?array $addr): ?array
    {
        if ($addr === null) {
            return null;
        }

        return [
            'line1' => isset($addr['line1']) && trim((string) $addr['line1']) !== '' ? trim((string) $addr['line1']) : null,
            'line2' => isset($addr['line2']) && trim((string) $addr['line2']) !== '' ? trim((string) $addr['line2']) : null,
            'city' => isset($addr['city']) && trim((string) $addr['city']) !== '' ? trim((string) $addr['city']) : null,
            'region' => isset($addr['region']) && trim((string) $addr['region']) !== '' ? trim((string) $addr['region']) : null,
            'postal_code' => isset($addr['postal_code']) && trim((string) $addr['postal_code']) !== '' ? trim((string) $addr['postal_code']) : null,
            'country_code' => isset($addr['country_code']) && (string) $addr['country_code'] !== ''
                ? strtoupper((string) $addr['country_code'])
                : null,
        ];
    }

    /**
     * @param  array<string, string|null>|null  $addr
     */
    public static function nullIfEmpty(?array $addr): ?array
    {
        if ($addr === null) {
            return null;
        }
        foreach (['line1', 'line2', 'city', 'region', 'postal_code', 'country_code'] as $k) {
            if (($addr[$k] ?? null) !== null && (string) $addr[$k] !== '') {
                return $addr;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $addr
     */
    public static function isNonEmpty(?array $addr): bool
    {
        return self::nullIfEmpty(self::normalize($addr)) !== null;
    }
}
