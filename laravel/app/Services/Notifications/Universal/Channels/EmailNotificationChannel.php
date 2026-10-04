<?php

namespace App\Services\Notifications\Universal\Channels;

use App\Services\Notifications\Universal\Contracts\EmailProviderInterface;
use App\Services\Notifications\Universal\DTOs\ChannelDeliveryReport;
use App\Services\Notifications\Universal\DTOs\NotificationMessage;
use App\Services\Notifications\Universal\DTOs\NotificationRecipient;
use App\Services\Notifications\Universal\Providers\Email\LaravelMailerEmailProvider;
use App\Services\Notifications\Universal\Providers\Email\MailgunEmailProvider;
use App\Services\Notifications\Universal\Providers\Email\PostmarkEmailProvider;
use App\Services\Notifications\Universal\Providers\Email\SendGridEmailProvider;
use App\Services\Notifications\Universal\Providers\Email\SesEmailProvider;
use InvalidArgumentException;

class EmailNotificationChannel extends AbstractNotificationChannel
{
    /** @var array<string, EmailProviderInterface> */
    protected array $providers = [];

    public function __construct(
        SendGridEmailProvider $sendGrid,
        MailgunEmailProvider $mailgun,
        PostmarkEmailProvider $postmark,
        SesEmailProvider $ses,
        LaravelMailerEmailProvider $laravelMailer
    ) {
        $this->providers = [
            'sendgrid' => $sendGrid,
            'mailgun' => $mailgun,
            'postmark' => $postmark,
            'ses' => $ses,
            'laravel' => $laravelMailer,
        ];
    }

    public function key(): string
    {
        return 'email';
    }

    public function label(): string
    {
        return 'Email Notification Gateway';
    }

    public function resolveProvider(?string $providerName = null): EmailProviderInterface
    {
        $name = strtolower($providerName ?? $this->activeProviderName());

        if (! isset($this->providers[$name])) {
            throw new InvalidArgumentException("Unknown email provider [{$name}]. Supported: ".implode(', ', array_keys($this->providers)));
        }

        return $this->providers[$name];
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function send(NotificationRecipient $recipient, NotificationMessage $message): ChannelDeliveryReport
    {
        $provider = $this->resolveProvider();

        return $provider->sendEmail($recipient, $message);
    }
}
