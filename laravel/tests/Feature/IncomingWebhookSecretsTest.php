<?php

namespace Tests\Feature;

use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * POST /api/v1/webhooks/incoming/{service} looked its secret up with env()
 * for whatever service the URL named. Under config:cache env() is null, so
 * every incoming webhook 404'd in production; uncached, any *_WEBHOOK_SECRET
 * was accepted — the outbound notification secret, which every receiver of
 * our webhooks holds, included. Senders are now listed in config.
 */
class IncomingWebhookSecretsTest extends TestCase
{
    /**
     * Load config/services.php with these environment variables, as
     * config:cache would. Laravel reads $_SERVER and $_ENV before getenv(),
     * so all three are set, and restored.
     *
     * @param  array<string, string>  $vars
     */
    private function servicesConfigWith(array $vars): array
    {
        $saved = [];
        foreach ($vars as $name => $value) {
            $saved[$name] = [$_SERVER[$name] ?? null, $_ENV[$name] ?? null, getenv($name)];
            $_SERVER[$name] = $_ENV[$name] = $value;
            putenv("{$name}={$value}");
        }

        try {
            return require config_path('services.php');
        } finally {
            foreach ($saved as $name => [$server, $env, $getenv]) {
                if ($server === null) {
                    unset($_SERVER[$name]);
                } else {
                    $_SERVER[$name] = $server;
                }
                if ($env === null) {
                    unset($_ENV[$name]);
                } else {
                    $_ENV[$name] = $env;
                }
                putenv($getenv === false ? $name : "{$name}={$getenv}");
            }
        }
    }

    private function postSigned(string $service, string $secret): TestResponse
    {
        $body = json_encode(['event' => 'gate.opened']);

        return $this->call('POST', "/api/v1/webhooks/incoming/{$service}", [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => 'sha256='.hash_hmac('sha256', $body, $secret),
        ], $body);
    }

    public function test_listed_senders_get_their_own_secret_in_config(): void
    {
        $config = $this->servicesConfigWith([
            'INCOMING_WEBHOOK_SERVICES' => ' Acme, github ,',
            'ACME_WEBHOOK_SECRET' => 'acme-secret',
            'GITHUB_WEBHOOK_SECRET' => 'github-secret',
        ]);

        $this->assertSame([
            'acme' => ['secret' => 'acme-secret'],
            'github' => ['secret' => 'github-secret'],
        ], $config['webhooks']);
    }

    public function test_no_senders_are_accepted_unless_listed(): void
    {
        $config = $this->servicesConfigWith(['INCOMING_WEBHOOK_SERVICES' => '']);

        $this->assertSame([], $config['webhooks']);
    }

    public function test_a_secret_meant_for_something_else_is_not_an_incoming_key(): void
    {
        // Held by every receiver of our outbound webhooks.
        config(['notifications.providers.webhook.signing_secret' => 'outbound-secret']);
        $_SERVER['NOTIFICATION_WEBHOOK_SECRET'] = $_ENV['NOTIFICATION_WEBHOOK_SECRET'] = 'outbound-secret';

        try {
            $this->postSigned('notification', 'outbound-secret')->assertNotFound();
        } finally {
            unset($_SERVER['NOTIFICATION_WEBHOOK_SECRET'], $_ENV['NOTIFICATION_WEBHOOK_SECRET']);
        }
    }

    public function test_a_listed_sender_is_verified_whatever_the_case_of_the_url(): void
    {
        config(['services.webhooks' => ['acme' => ['secret' => 'acme-secret']]]);

        $this->postSigned('ACME', 'acme-secret')->assertOk();
        $this->postSigned('acme', 'wrong-secret')->assertUnauthorized();
    }
}
