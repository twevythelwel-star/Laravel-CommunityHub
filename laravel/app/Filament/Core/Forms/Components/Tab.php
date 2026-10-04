<?php

namespace App\Filament\Core\Forms\Components;

class Tab
{
    protected string $label;

    protected ?string $icon = null;

    protected array $schema = [];

    public static function make(string $label): static
    {
        $t = new static;
        $t->label = $label;

        return $t;
    }

    public function icon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function schema(array $schema): static
    {
        $this->schema = $schema;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function getSchema(): array
    {
        return $this->schema;
    }
}
