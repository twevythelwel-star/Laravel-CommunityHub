<?php

namespace App\Services;

use App\Models\User;

/**
 * What a client needs to join its websocket channels over Reverb: the web
 * layout (session cookie) and token clients such as the mobile shell and the
 * handheld scanners (Sanctum). Both get the same channel list from here, and
 * the server still decides every subscription (routes/channels.php).
 *
 * The app key is public by design; the secret never leaves the server.
 */
class RealtimeConfig
{
    public function enabled(): bool
    {
        return config('broadcasting.default') === 'reverb'
            && filled(config('broadcasting.connections.reverb.key'));
    }

    /** @return array<string, mixed>|null */
    public function forBrowser(User $user): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        return [
            ...$this->connection(),
            'userChannel' => "user.{$user->id}",
            'gateFeed' => $this->receivesGateFeed($user),
        ];
    }

    /**
     * For clients that speak the Pusher protocol directly (Echo, or the native
     * Pusher SDKs), so channel names are given as sent on the wire.
     *
     * @return array<string, mixed>|null
     */
    public function forDevice(User $user): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        $channels = [
            ['name' => "private-user.{$user->id}", 'events' => ['inbox.message', 'visitor.checked-in']],
            ['name' => 'private-community-alerts', 'events' => ['security.alert']],
        ];

        if ($this->receivesGateFeed($user)) {
            $channels[] = ['name' => 'private-gatehouse-stream', 'events' => ['visitor.checked-in', 'access.recorded']];
        }

        return [
            'broadcaster' => 'reverb',
            ...$this->connection(),
            // Reverb admits only listed origins (config/reverb.php), and a
            // native client sends none unless told: send this as the Origin
            // header when opening the socket.
            'origin' => $this->siteOrigin(),
            'authEndpoint' => url('/api/broadcasting/auth'),
            'channels' => $channels,
        ];
    }

    /** @return array{key: string, host: string, port: int, scheme: string} */
    private function connection(): array
    {
        return [
            'key' => config('broadcasting.connections.reverb.key'),
            'host' => config('broadcasting.browser.host'),
            'port' => (int) config('broadcasting.browser.port'),
            'scheme' => config('broadcasting.browser.scheme'),
        ];
    }

    private function siteOrigin(): string
    {
        $url = parse_url((string) config('app.url'));
        $port = isset($url['port']) ? ":{$url['port']}" : '';

        return ($url['scheme'] ?? 'https').'://'.($url['host'] ?? 'localhost').$port;
    }

    private function receivesGateFeed(User $user): bool
    {
        return $user->can('scanPasses');
    }
}
