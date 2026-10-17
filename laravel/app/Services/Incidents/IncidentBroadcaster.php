<?php

namespace App\Services\Incidents;

use App\Actions\Communications\BroadcastEmergencyAction;
use App\Models\User;

/**
 * Announces an incident, an update or an all-clear to the whole community through the
 * existing emergency broadcast (stored notice, in-app, push, SMS, WhatsApp, email), on the
 * channels the officer picked.
 */
class IncidentBroadcaster
{
    /** Channels used when the officer did not pick any. */
    public const DEFAULT_CHANNELS = ['in_app', 'push', 'sms', 'whatsapp', 'email'];

    public function __construct(private readonly BroadcastEmergencyAction $broadcast) {}

    /** @param  list<string>|null  $channels */
    public function send(User $sender, string $title, string $content, string $severity, ?array $channels): void
    {
        $channels = $channels ?: self::DEFAULT_CHANNELS;

        $this->broadcast->execute($sender, [
            'title' => $title,
            'content' => $content,
            'severity' => $severity,
            'audience' => 'all',
            'send_in_app' => in_array('in_app', $channels, true),
            'send_push' => in_array('push', $channels, true),
            'send_sms' => in_array('sms', $channels, true),
            'send_whatsapp' => in_array('whatsapp', $channels, true),
            'send_email' => in_array('email', $channels, true),
        ]);
    }
}
