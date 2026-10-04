<?php

namespace App\Filament\Core\Widgets;

class Stat
{
    protected string $label;

    protected string|int $value;

    protected ?string $description = null;

    protected ?string $descriptionIcon = null;

    protected ?string $color = null;

    public static function make(string $label, string|int $value): static
    {
        $stat = new static;
        $stat->label = $label;
        $stat->value = $value;

        return $stat;
    }

    public function description(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function descriptionIcon(string $icon): static
    {
        $this->descriptionIcon = $icon;

        return $this;
    }

    public function color(string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getValue(): string|int
    {
        return $this->value;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getDescriptionIcon(): ?string
    {
        return $this->descriptionIcon;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }
}

abstract class StatsOverviewWidget
{
    abstract protected function getStats(): array;

    public function renderStats(): array
    {
        return $this->getStats();
    }
}
