<?php

namespace App\Filament\Core\Forms\Components;

class Section
{
    protected string $heading;

    protected ?string $description = null;

    protected ?string $icon = null;

    protected array $schema = [];

    protected bool $collapsible = false;

    protected bool $collapsed = false;

    public static function make(string $heading): static
    {
        $sec = new static;
        $sec->heading = $heading;

        return $sec;
    }

    public function description(string $desc): static
    {
        $this->description = $desc;

        return $this;
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

    public function collapsible(bool $collapsible = true): static
    {
        $this->collapsible = $collapsible;

        return $this;
    }

    public function collapsed(bool $collapsed = true): static
    {
        $this->collapsed = $collapsed;

        return $this;
    }

    public function getHeading(): string
    {
        return $this->heading;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function getSchema(): array
    {
        return $this->schema;
    }

    public function isCollapsible(): bool
    {
        return $this->collapsible;
    }

    public function isCollapsed(): bool
    {
        return $this->collapsed;
    }
}
