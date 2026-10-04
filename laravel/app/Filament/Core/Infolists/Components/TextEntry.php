<?php

namespace App\Filament\Core\Infolists\Components;

class TextEntry extends Entry
{
    protected bool $copyable = false;

    protected ?string $badgeColor = null;

    public static function make(string $name): static
    {
        $entry = new static;
        $entry->name = $name;

        return $entry;
    }

    public function copyable(bool $copyable = true): static
    {
        $this->copyable = $copyable;

        return $this;
    }

    public function badge(): static
    {
        $this->badgeColor = 'primary';

        return $this;
    }

    public function isCopyable(): bool
    {
        return $this->copyable;
    }

    public function getBadgeColor(): ?string
    {
        return $this->badgeColor;
    }
}
