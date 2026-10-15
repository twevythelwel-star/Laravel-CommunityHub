<?php

declare(strict_types=1);

namespace App\Services\Webhooks;

use Illuminate\Http\Request;

/**
 * Checks that a webhook from an outside service was signed with that
 * service's shared secret (config('services.webhooks.<service>.secret')).
 * The signature is an HMAC-SHA256 of the raw body, with or without a
 * "sha256=" prefix, in any of the headers the common senders use.
 */
class IncomingWebhookVerifier
{
    private const SIGNATURE_HEADERS = ['X-Webhook-Signature', 'X-Hub-Signature-256', 'Stripe-Signature'];

    /** Whether a secret is configured; without one there is nothing to verify against, so fail closed. */
    public function isConfigured(string $service): bool
    {
        return $this->secret($service) !== '';
    }

    public function verify(Request $request, string $service): bool
    {
        $secret = $this->secret($service);
        if ($secret === '') {
            return false;
        }

        $signature = $this->signature($request);
        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals('sha256='.$expected, $signature) || hash_equals($expected, $signature);
    }

    private function signature(Request $request): string
    {
        foreach (self::SIGNATURE_HEADERS as $header) {
            if ($request->hasHeader($header)) {
                return (string) $request->header($header);
            }
        }

        return '';
    }

    /** Senders are listed in config/services.php: an env lookup here was null under config:cache. */
    private function secret(string $service): string
    {
        return (string) config('services.webhooks.'.strtolower($service).'.secret');
    }
}
