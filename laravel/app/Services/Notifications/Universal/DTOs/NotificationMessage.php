<?php

namespace App\Services\Notifications\Universal\DTOs;

class NotificationMessage
{
    public function __construct(
        public string $title,
        public string $body,
        public ?string $actionUrl = null,
        public string $priority = 'normal', // low, normal, high, urgent
        public string $category = 'general', // billing, security, pass, announcement, system
        public array $data = [],
        public ?string $templateId = null,
        public ?string $sound = 'default',
        public ?string $imageUrl = null,
    ) {}

    public static function create(
        string $title,
        string $body,
        ?string $actionUrl = null,
        string $priority = 'normal',
        string $category = 'general',
        array $data = []
    ): self {
        return new self(
            title: $title,
            body: $body,
            actionUrl: $actionUrl,
            priority: $priority,
            category: $category,
            data: $data
        );
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'action_url' => $this->actionUrl,
            'priority' => $this->priority,
            'category' => $this->category,
            'data' => $this->data,
            'template_id' => $this->templateId,
            'sound' => $this->sound,
            'image_url' => $this->imageUrl,
        ];
    }
}
