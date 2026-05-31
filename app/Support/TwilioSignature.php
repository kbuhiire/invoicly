<?php

namespace App\Support;

/**
 * Validates Twilio's X-Twilio-Signature header for inbound status callbacks.
 *
 * Twilio signs requests as:
 *   base64( HMAC-SHA1( authToken, fullUrl + concat(sort(params) as key.value) ) )
 *
 * @see https://www.twilio.com/docs/usage/security#validating-requests
 */
class TwilioSignature
{
    /**
     * @param  array<string, mixed>  $params  the POSTed form parameters
     */
    public static function isValid(string $authToken, string $url, array $params, string $signature): bool
    {
        if ($authToken === '' || $signature === '') {
            return false;
        }

        ksort($params);
        $data = $url;
        foreach ($params as $key => $value) {
            $data .= $key.(is_array($value) ? implode('', $value) : (string) $value);
        }

        $expected = base64_encode(hash_hmac('sha1', $data, $authToken, true));

        return hash_equals($expected, $signature);
    }
}
