<?php

namespace App\Services\NotificationEngine;

use App\Models\AmenityBooking;
use App\Models\User;
use App\Models\Visitor;
use App\Services\NotificationEngine\Channels\EmailChannel;
use App\Services\NotificationEngine\Channels\InAppChannel;
use App\Services\NotificationEngine\Channels\PushChannel;
use App\Services\NotificationEngine\Channels\SmsChannel;
use App\Services\NotificationEngine\Channels\WhatsAppChannel;
use App\Services\NotificationEngine\Contracts\NotificationChannelInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Enterprise Multi-Channel Notification Engine.
 *
 * Guarantees that:
 *  1. Any unconfigured provider returns 'unavailable' or 'failed', never a false 'sent'.
 *  2. Logs strictly mask contact info (+1******1234, j***e@domain.com) and NEVER
 *     log raw phone numbers or sensitive message text.
 *  3. Centralized status reports across Email, SMS, WhatsApp, Push, and In-App.
 */
class NotificationEngine
{
    /** @var array<string, NotificationChannelInterface> */
    protected array $channels = [];

    public function __construct(
        EmailChannel $email,
        SmsChannel $sms,
        WhatsAppChannel $whatsApp,
        PushChannel $push,
        InAppChannel $inApp,
    ) {
        $this->channels = [
            'email' => $email,
            'sms' => $sms,
            'whatsapp' => $whatsApp,
            'push' => $push,
            'in_app' => $inApp,
        ];
    }

    /**
     * Retrieve a registered channel instance by key.
     */
    public function channel(string $name): NotificationChannelInterface
    {
        $key = strtolower($name);

        if (! isset($this->channels[$key])) {
            throw new InvalidArgumentException("Notification channel [{$name}] is not supported.");
        }

        return $this->channels[$key];
    }

    /**
     * Check whether a specific channel's external provider is configured and ready.
     */
    public function isChannelConfigured(string $name): bool
    {
        $key = strtolower($name);

        return isset($this->channels[$key]) && $this->channels[$key]->isConfigured();
    }

    /**
     * Comprehensive health and configuration report across all 5 channels.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getChannelsStatus(): array
    {
        $report = [];

        foreach ($this->channels as $key => $channel) {
            $isConfigured = $channel->isConfigured();
            $report[$key] = [
                'channel' => $key,
                'label' => $channel->label(),
                'provider' => $channel->provider(),
                'configured' => $isConfigured,
                'status' => $isConfigured ? 'available' : 'unavailable',
            ];
        }

        return $report;
    }

    /**
     * Dispatch a notification payload to one or more channels.
     *
     * @param  array<string>  $channelKeys
     * @return array<string, ChannelDispatchResult>
     */
    public function send(array $channelKeys, NotificationPayload $payload): array
    {
        $results = [];

        foreach ($channelKeys as $key) {
            $key = strtolower(trim($key));

            if (! isset($this->channels[$key])) {
                $results[$key] = ChannelDispatchResult::failed($key, "Unknown notification channel [{$key}].");

                continue;
            }

            $channel = $this->channels[$key];

            if (! $channel->isConfigured()) {
                $result = ChannelDispatchResult::unavailable(
                    $key,
                    "Channel [{$channel->label()}] provider is not configured."
                );
            } else {
                $result = $channel->send($payload);
            }

            $results[$key] = $result;
            $this->auditLog($key, $result->status, $payload->recipient, $payload, $result->reason);
        }

        return $results;
    }

    /**
     * Tell a resident their amenity booking was cancelled by someone else.
     *
     * Email and SMS only, by the resident's own preferences (email on and SMS
     * off unless they changed them). Never the in-app channel: that posts a
     * community notice, which would show one resident's booking to everyone.
     * Each channel gets its own payload so SMS goes to the phone and email to
     * the address.
     *
     * @return array<string, ChannelDispatchResult> keyed by channel; empty when
     *                                              the resident wants neither
     */
    public function notifyBookingCancelled(AmenityBooking $booking): array
    {
        $resident = $booking->user;
        $slot = AmenityBooking::SLOTS[$booking->slot] ?? null;
        $when = $booking->booked_on->format('D j M Y').($slot ? ", {$slot['starts']}-{$slot['ends']}" : '');

        $body = "Your booking {$booking->reference} for {$booking->amenity->name} on {$when} has been cancelled by the community office."
            .($booking->cancellation_reason ? " Reason: {$booking->cancellation_reason}." : '')
            .' Please contact the office if you have any questions.';

        return $this->notifyResident($resident, 'Amenity booking cancelled', $body, [
            'amenity_booking_id' => $booking->id,
            'notice_type' => 'amenity_booking_cancelled',
        ]);
    }

    /**
     * Send a personal message to one resident by email and/or SMS, as their
     * own notification settings say (email on and SMS off unless changed).
     *
     * Never the in-app channel: it posts a community notice, which everyone
     * sees. Each channel gets its own payload, so SMS goes to the phone and
     * email to the address.
     *
     * @param  array<string, mixed>  $metadata
     * @return array<string, ChannelDispatchResult> keyed by channel; empty when
     *                                              the resident wants neither
     */
    private function notifyResident(User $resident, string $title, string $body, array $metadata = []): array
    {
        $preferences = $resident->preferences;

        $recipients = array_filter([
            'email' => ($preferences?->notify_email ?? true) ? $resident->email : null,
            'sms' => ($preferences?->notify_sms ?? false) ? $resident->phone : null,
        ], fn (?string $to) => filled($to));

        $results = [];
        foreach ($recipients as $channel => $to) {
            $results += $this->send([$channel], new NotificationPayload(
                title: $title,
                body: $body,
                recipient: $to,
                user: $resident,
                metadata: $metadata,
            ));
        }

        return $results;
    }

    /**
     * Dispatch a visitor pass across requested channels with strict privacy logging.
     *
     * @param  array<string>  $channels
     * @return array<string, ChannelDispatchResult>
     */
    public function sendVisitorPass(Visitor $visitor, array $channels = ['email'], ?string $qrCodePath = null): array
    {
        $expectedAtFormatted = $visitor->expected_at ? $visitor->expected_at->format('l, F j, Y \a\t g:i A') : 'N/A';
        $mediaUrl = $qrCodePath ? Storage::disk('public')->url($qrCodePath) : null;

        $body = "Hello {$visitor->name}, you have been registered as a visitor to the community. "
            ."Your guest pass is available at: {$visitor->guestPassUrl()}. "
            ."Expected arrival: {$expectedAtFormatted}. Host: {$visitor->homeowner_name}";

        $payload = new NotificationPayload(
            title: 'Community Hub Guest Pass',
            body: $body,
            recipient: (string) $visitor->contact,
            visitor: $visitor,
            actionUrl: $visitor->guestPassUrl(),
            mediaUrl: $mediaUrl,
            templateVariables: [
                '1' => $visitor->name,
                '2' => $visitor->guestPassUrl(),
                '3' => $expectedAtFormatted,
                '4' => (string) $visitor->homeowner_name,
            ],
            metadata: [
                'visitor_id' => $visitor->id,
                'pass_type' => 'visitor_pass',
            ]
        );

        return $this->send($channels, $payload);
    }

    /**
     * Tell the host that their visitor has arrived.
     *
     * Only the host, by the host's own email/SMS settings. This used to post
     * through the in-app channel, which writes a community notice with no
     * audience, so every arrival (visitor, gate, vehicle) was shown to the
     * whole estate. The visitor's notify_* flags are about sending the visitor
     * their pass and no longer decide how the host is told. The host still
     * sees the check-in on their Visitors page.
     *
     * @return array<string, ChannelDispatchResult>
     */
    public function dispatchArrivalNotice(Visitor $visitor, string $gate = 'Main Gate'): array
    {
        $host = $visitor->homeowner;
        if (! $host) {
            return [];
        }

        $vehicleInfo = $visitor->vehicle ? " (Vehicle: {$visitor->vehicle})" : '';

        return $this->notifyResident(
            $host,
            "Visitor Arrived: {$visitor->name}",
            "{$visitor->name} has arrived and checked in at {$gate}{$vehicleInfo}.",
            ['visitor_id' => $visitor->id, 'gate' => $gate, 'event' => 'visitor_checked_in'],
        );
    }

    /**
     * Broadcast an in-app or multi-channel notice to residents.
     *
     * @param  array<string>|null  $targetRoles
     * @param  array<string>  $channels
     * @return array<string, ChannelDispatchResult>
     */
    public function broadcast(string $title, string $message, ?array $targetRoles = null, array $channels = ['in_app']): array
    {
        $payload = new NotificationPayload(
            title: $title,
            body: $message,
            targetRoles: $targetRoles,
            metadata: ['broadcast' => true]
        );

        return $this->send($channels, $payload);
    }

    /**
     * Mask contact for logging: phone or email.
     */
    public function maskRecipient(?string $recipient): string
    {
        return PrivacyMasker::maskRecipient($recipient);
    }

    /**
     * Centralized security & privacy audit log.
     *
     * Guarantees raw phone numbers and message text NEVER appear in application logs.
     */
    protected function auditLog(string $channel, string $status, ?string $recipient, NotificationPayload $payload, ?string $reason = null): void
    {
        $maskedContact = PrivacyMasker::maskRecipient($recipient);
        $summary = PrivacyMasker::auditSummary($payload);

        $context = [
            'channel' => $channel,
            'status' => $status,
            'recipient' => $maskedContact,
            'digest' => $summary['digest'],
            'length' => $summary['length'],
        ];

        if ($reason) {
            $context['reason'] = $reason;
        }

        if ($status === 'sent') {
            Log::info("[NotificationEngine] Dispatched {$channel} notification", $context);
        } elseif ($status === 'unavailable') {
            Log::warning("[NotificationEngine] Channel {$channel} unavailable: provider unconfigured", $context);
        } else {
            Log::error("[NotificationEngine] Channel {$channel} dispatch failed", $context);
        }
    }
}
