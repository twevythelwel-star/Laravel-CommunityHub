<?php

namespace App\Filament\Core\Tables\Components;

class Action
{
    protected string $name;

    protected ?string $label = null;

    protected ?string $icon = null;

    protected ?string $color = null;

    protected bool $requiresConfirmation = false;

    protected ?string $modalHeading = null;

    protected ?string $modalSubheading = null;

    public static function make(string $name): static
    {
        $act = new static;
        $act->name = $name;

        return $act;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function icon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function color(string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function requiresConfirmation(bool $confirm = true): static
    {
        $this->requiresConfirmation = $confirm;

        return $this;
    }

    public function modalHeading(string $heading): static
    {
        $this->modalHeading = $heading;

        return $this;
    }

    public function modalSubheading(string $sub): static
    {
        $this->modalSubheading = $sub;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label ?? ucwords(str_replace('_', ' ', $this->name));
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function isConfirmationRequired(): bool
    {
        return $this->requiresConfirmation;
    }

    public function getModalHeading(): ?string
    {
        return $this->modalHeading;
    }

    public function getModalSubheading(): ?string
    {
        return $this->modalSubheading;
    }
}
