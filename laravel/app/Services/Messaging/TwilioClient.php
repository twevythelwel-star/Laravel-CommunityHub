<?php

namespace App\Services\Messaging;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends a message through Twilio's Programmable Messaging REST API.
 *
 * Laravel's HTTP client rather than the Twilio SDK: it is one endpoint, it
 * adds no dependency, and Http::fake() makes it testable without the network.
 *
 * Logs carry the masked number and Twilio's message SID. The body is never
 * logged: it contains the visitor's name, the host's name and a guest-pass URL
 * that works for anyone holding it.
 */
class TwilioClient
{
    private const ENDPOINT = 'https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json';

    public function hasCredentials(): bool
    {
        return filled(config('services.twilio.sid')) && filled(config('services.twilio.token'));
    }

    /**
     * @param  array<string, string>  $params  Twilio parameters beyond To and From: Body, MediaUrl, ContentSid, ContentVariables.
     * @return string The SID of the message Twilio accepted.
     *
     * @throws MessageNotSent
     */
    public function send(string $channel, string $to, string $from, array $params): string
    {
        $sid = (string) config('services.twilio.sid');

        try {
            $response = Http::asForm()
                ->withBasicAuth($sid, (string) config('services.twilio.token'))
                ->timeout(15)
                ->post(sprintf(self::ENDPOINT, $sid), ['To' => $to, 'From' => $from, ...$params]);
        } catch (ConnectionException) {
            Log::warning("{$channel} could not reach Twilio", ['to' => PhoneNumber::mask($to)]);

            throw MessageNotSent::unreachable($channel);
        }

        if ($response->failed()) {
            $code = $response->json('code');

            Log::warning("{$channel} refused by Twilio", [
                'to' => PhoneNumber::mask($to),
                'status' => $response->status(),
                'code' => $code,
            ]);

            throw MessageNotSent::rejected($channel, $response->status(), is_numeric($code) ? (int) $code : null);
        }

        $messageSid = (string) $response->json('sid');

        Log::info("{$channel} accepted by Twilio", [
            'to' => PhoneNumber::mask($to),
            'sid' => $messageSid,
        ]);

        return $messageSid;
    }
}
