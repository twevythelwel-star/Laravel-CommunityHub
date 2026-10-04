<?php

namespace App\Filament\Core\Forms\Components;

abstract class Component
{
    protected string $name = '';

    protected ?string $label = null;

    protected bool $required = false;

    protected mixed $default = null;

    protected ?string $helperText = null;

    protected ?string $placeholder = null;

    protected array $rules = [];

    protected int $columnSpan = 1;

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

    public function required(bool $required = true): static
    {
        $this->required = $required;

        return $this;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function default(mixed $default): static
    {
        $this->default = $default;

        return $this;
    }

    public function getDefault(): mixed
    {
        return $this->default;
    }

    public function helperText(string $text): static
    {
        $this->helperText = $text;

        return $this;
    }

    public function getHelperText(): ?string
    {
        return $this->helperText;
    }

    public function placeholder(string $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function getPlaceholder(): ?string
    {
        return $this->placeholder;
    }

    public function columnSpan(int|string $span): static
    {
        $this->columnSpan = is_int($span) ? $span : 2;

        return $this;
    }

    public function columnSpanFull(): static
    {
        $this->columnSpan = 2;

        return $this;
    }

    public function getColumnSpan(): int
    {
        return $this->columnSpan;
    }
}
