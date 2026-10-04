<?php

namespace App\Filament\Core\Tables\Components;

class SelectFilter
{
    protected string $name;

    protected ?string $label = null;

    protected array $options = [];

    public static function make(string $name): static
    {
        $f = new static;
        $f->name = $name;

        return $f;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function options(array $options): static
    {
        $this->options = $options;

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

    public function getOptions(): array
    {
        return $this->options;
    }
}
