<?php

namespace App\Services\NotificationEngine;

use App\Models\User;
use App\Models\Visitor;

/**
 * Immutable payload passed to notification channels.
 */
class NotificationPayload
{
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly ?string $recipient = null, // Email, phone, token
        // Who a personal message is to (email/SMS fall back to their address).
        // Never used as a community notice's author: see $author.
        public readonly ?User $user = null,
        public readonly ?Visitor $visitor = null,
        public readonly ?string $actionUrl = null,
        public readonly ?string $mediaUrl = null,
        public readonly ?string $templateId = null,
        public readonly array $templateVariables = [],
        // Community notices only: the roles to show it to...
        public readonly ?array $targetRoles = null,
        public readonly array $metadata = [],
        // ...or everyone, which must be asked for explicitly.
        public readonly bool $toEveryone = false,
        // Community notices only: who the notice is from.
        public readonly ?User $author = null,
    ) {}

    public function contentDigest(): string
    {
        return hash('sha256', $this->title.'|'.$this->body.'|'.($this->actionUrl ?? ''));
    }

    public function contentLength(): int
    {
        return mb_strlen($this->title.' '.$this->body);
    }
}
