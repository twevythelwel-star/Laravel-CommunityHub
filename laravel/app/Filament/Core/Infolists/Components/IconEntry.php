<?php

namespace App\Filament\Core\Infolists\Components;

class IconEntry extends Entry
{
    protected ?string $icon = null;

    public static function make(string $name): static
    {
        $entry = new static;
        $entry->name = $name;

        return $entry;
    }

    public function icon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }
}
