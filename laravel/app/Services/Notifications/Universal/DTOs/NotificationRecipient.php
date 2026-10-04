<?php

namespace App\Services\Notifications\Universal\DTOs;

use App\Models\User;

class NotificationRecipient
{
    public function __construct(
        public ?int $userId = null,
        public ?string $name = null,
        public ?string $email = null,
        public ?string $phone = null,
        public array $deviceTokens = [],
        public ?string $webhookUrl = null,
        public ?string $slackWebhookUrl = null,
        public ?string $teamsWebhookUrl = null,
        public ?string $tenantId = null,
        public array $metadata = []
    ) {}

    public static function fromUser(User $user): self
    {
        return new self(
            userId: $user->id,
            name: $user->name ?? $user->first_name.' '.$user->last_name,
            email: $user->email,
            phone: $user->phone ?? null,
            deviceTokens: (array) ($user->device_tokens ?? []),
            tenantId: $user->tenant_id ?? null,
            metadata: [
                'role' => $user->role instanceof \BackedEnum ? $user->role->value : (string) $user->role,
                'status' => $user->status ?? 'Active',
            ]
        );
    }

    public static function direct(
        ?string $email = null,
        ?string $phone = null,
        ?string $name = null,
        array $deviceTokens = []
    ): self {
        return new self(
            name: $name,
            email: $email,
            phone: $phone,
            deviceTokens: $deviceTokens
        );
    }

    public function maskedEmail(): string
    {
        if (empty($this->email)) {
            return 'N/A';
        }

        $parts = explode('@', $this->email);
        $name = $parts[0];
        $domain = $parts[1] ?? '';

        $len = strlen($name);
        if ($len <= 2) {
            $masked = $name[0].'*';
        } else {
            $masked = $name[0].str_repeat('*', $len - 2).$name[$len - 1];
        }

        return $masked.'@'.$domain;
    }

    public function maskedPhone(): string
    {
        if (empty($this->phone)) {
            return 'N/A';
        }

        $len = strlen($this->phone);
        if ($len <= 4) {
            return '****';
        }

        return substr($this->phone, 0, 2).str_repeat('*', max(0, $len - 6)).substr($this->phone, -4);
    }
}
