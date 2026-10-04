<?php

namespace App\Filament\Core\Forms\Components;

class Tabs
{
    protected string $id;

    protected array $tabs = [];

    public static function make(string $id): static
    {
        $t = new static;
        $t->id = $id;

        return $t;
    }

    public function tabs(array $tabs): static
    {
        $this->tabs = $tabs;

        return $this;
    }

    public function getTabs(): array
    {
        return $this->tabs;
    }

    public function getId(): string
    {
        return $this->id;
    }
}
