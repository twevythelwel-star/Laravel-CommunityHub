<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates X-Twilio-Signature: base64 HMAC-SHA1, keyed with the account's
 * Auth Token, over the full URL followed by each POST field (sorted by name)
 * as name+value. Twilio signs with the Auth Token only — never an API key
 * secret — so without one no callback can be trusted.
 */
class VerifyTwilioSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) config('services.twilio.token');

        if ($token === '') {
            Log::warning('Twilio webhook rejected: no Auth Token configured to verify it');
            abort(403, 'Twilio is not configured');
        }

        $signature = (string) $request->header('X-Twilio-Signature');

        if ($signature === '') {
            Log::warning('Twilio webhook rejected: missing X-Twilio-Signature header');
            abort(403, 'Missing Twilio Signature');
        }

        if (! hash_equals(self::sign($request->fullUrl(), $request->post(), $token), $signature)) {
            Log::warning('Twilio webhook rejected: invalid X-Twilio-Signature', ['url' => $request->fullUrl()]);
            abort(403, 'Invalid Twilio Signature');
        }

        return $next($request);
    }

    /**
     * The signature Twilio sends for this URL and these POST fields.
     *
     * @param  array<string, mixed>  $fields
     */
    public static function sign(string $url, array $fields, string $token): string
    {
        ksort($fields);

        $signed = $url;
        foreach ($fields as $name => $value) {
            $signed .= $name.(is_array($value) ? implode('', $value) : $value);
        }

        return base64_encode(hash_hmac('sha1', $signed, $token, true));
    }
}
