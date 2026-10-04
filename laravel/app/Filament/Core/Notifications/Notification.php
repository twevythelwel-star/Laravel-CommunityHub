<?php

namespace App\Filament\Core\Notifications;

class Notification
{
    protected string $title = '';

    protected ?string $body = null;

    protected string $status = 'info';

    protected ?int $duration = 4000;

    public static function make(): static
    {
        return new static;
    }

    public function title(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function body(string $body): static
    {
        $this->body = $body;

        return $this;
    }

    public function success(): static
    {
        $this->status = 'success';

        return $this;
    }

    public function warning(): static
    {
        $this->status = 'warning';

        return $this;
    }

    public function danger(): static
    {
        $this->status = 'error';

        return $this;
    }

    public function info(): static
    {
        $this->status = 'info';

        return $this;
    }

    public function duration(int $ms): static
    {
        $this->duration = $ms;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'status' => $this->status,
            'duration' => $this->duration,
        ];
    }

    public function send(): static
    {
        // Dispatches event or stores in session for flash alerts
        session()->flash('filament_notification', $this->toArray());

        return $this;
    }
}
