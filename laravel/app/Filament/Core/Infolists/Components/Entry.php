<?php

namespace App\Filament\Core\Infolists\Components;

abstract class Entry
{
    protected string $name;

    protected ?string $label = null;

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label ?? ucwords(str_replace('_', ' ', $this->name));
    }

    public function getName(): string
    {
        return $this->name;
    }
}
